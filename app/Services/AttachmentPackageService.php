<?php

namespace App\Services;

use App\Models\AttachmentPackageRun;
use App\Models\AttachmentPackageSection;
use App\Models\Project;
use App\Models\RequirementEvidence;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class AttachmentPackageService
{
    public function generateAndUpload(AttachmentPackageRun $run): AttachmentPackageRun
    {
        $workDir = storage_path('app/tmp/attachment-runs/'.$run->id);
        try {
            $this->touchStage($run, 'Preparando proyecto', 5);
            $project = $run->project()->with('requisitos')->firstOrFail();
            $userId = $run->user_id;

            $this->touchStage($run, 'Resolviendo carpeta 02 Cargue', 12);
            $driveFolderId = $this->drive()->ensureProjectSubfolder($project, '02 Cargue', $userId);
            if (! $driveFolderId) {
                throw new \RuntimeException('No se pudo resolver la carpeta 02 Cargue en Drive.');
            }

            $this->touchStage($run, 'Calculando version', 18);
            $versionMode = (string) data_get($run->meta, 'version_mode', 'next');
            $version = $this->resolvePackageVersion($driveFolderId, $userId, $versionMode);
            $downloadDir = $workDir.'/downloads';
            $outputDir = $workDir.'/output';
            File::ensureDirectoryExists($downloadDir);
            File::ensureDirectoryExists($outputDir);

            $this->touchStage($run, 'Descargando evidencias desde Drive', 40);
            $documents = $this->buildDocumentsManifest($project, $downloadDir, $userId, $run);
            if (empty($documents)) {
                throw new \RuntimeException('No fue posible descargar evidencias desde Drive para generar el paquete.');
            }
            $projectBase = $this->sanitizeFileBase('Adjuntos '.($project->nombre ?: 'Proyecto'));
            $zipFilename = $projectBase.' V'.$version.'.zip';
            $zipPath = $outputDir.'/'.$zipFilename;
            $manifestPath = $workDir.'/manifest.json';

            file_put_contents($manifestPath, json_encode([
                'version_number' => $version,
                'documents' => $documents,
                'output_dir' => $outputDir,
                'logo_path' => public_path('img/logo.jpg'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $this->touchStage($run, 'Generando PDFs con Python', 62);
            $pythonResult = $this->runPythonGenerator($manifestPath);
            $generatedPdfs = $pythonResult['pdf_filenames'] ?? [];
            $missingReport = $pythonResult['missing_report'] ?? null;
            $generalReport = $pythonResult['general_report'] ?? null;
            $missingCount = (int) ($pythonResult['missing_count'] ?? 0);
            if (empty($generatedPdfs)) {
                throw new \RuntimeException('El generador no produjo archivos PDF.');
            }

            $outputType = count($documents) === 1 ? 'pdf' : 'zip';
            if ($outputType === 'pdf') {
                $outputFilename = (string) $generatedPdfs[0];
                $outputPath = $outputDir.'/'.$outputFilename;
                $mimeType = 'application/pdf';
                $stageLabel = 'Subiendo PDF a Drive';
            } else {
                $this->touchStage($run, 'Empaquetando ZIP', 76);
                $this->buildZip($zipPath, $outputDir, $generatedPdfs, $missingReport, $generalReport);
                $outputFilename = $zipFilename;
                $outputPath = $zipPath;
                $mimeType = 'application/zip';
                $stageLabel = 'Subiendo ZIP a Drive';
            }

            $outputSizeBytes = (int) (filesize($outputPath) ?: 0);
            $run->forceFill([
                'version_number' => $version,
                'output_type' => $outputType,
                'output_filename' => $outputFilename,
                'output_local_path' => null,
                'zip_filename' => $outputType === 'zip' ? $outputFilename : null,
                'zip_local_path' => null,
            ])->save();

            $this->touchStage($run, $stageLabel, 90);
            $lastUploadPercent = -1;
            $uploaded = $this->drive()->uploadLocalFileToFolder(
                $driveFolderId,
                $outputFilename,
                $outputPath,
                $mimeType,
                $userId,
                function (int $uploadedBytes, int $totalBytes) use ($run, &$lastUploadPercent, $outputSizeBytes, $stageLabel): void {
                    $total = $totalBytes > 0 ? $totalBytes : max(1, $outputSizeBytes);
                    $detailPercent = (int) floor(($uploadedBytes / $total) * 100);
                    $detailPercent = max(0, min(100, $detailPercent));
                    if ($detailPercent === $lastUploadPercent) {
                        return;
                    }
                    $lastUploadPercent = $detailPercent;

                    $overallPercent = 90 + (int) floor($detailPercent / 10);
                    $uploadedMb = round($uploadedBytes / 1048576, 1);
                    $totalMb = round($total / 1048576, 1);
                    $label = $stageLabel.' ('.$uploadedMb.'MB/'.$totalMb.'MB)';
                    $this->touchStage($run, $label, $overallPercent, $detailPercent);
                }
            );

            $existingMeta = is_array($run->fresh()->meta) ? $run->fresh()->meta : [];

            $run->update([
                'version_number' => $version,
                'output_type' => $outputType,
                'output_filename' => $outputFilename,
                'output_local_path' => null,
                'zip_filename' => $outputType === 'zip' ? $outputFilename : null,
                'zip_local_path' => null,
                'drive_folder_id' => $driveFolderId,
                'drive_file_id' => $uploaded['id'] ?? null,
                'generated_pdf_count' => count($generatedPdfs),
                'missing_count' => $missingCount,
                'meta' => array_merge($existingMeta, [
                    'pdf_filenames' => $generatedPdfs,
                    'missing_report' => $missingReport,
                    'general_report' => $generalReport,
                    'python_output' => $pythonResult,
                    'selected_documents' => $run->selected_documents ?: [],
                    'stage_label' => 'Finalizado',
                    'stage_percent' => 100,
                    'heartbeat_at' => now()->toDateTimeString(),
                ]),
            ]);

            return $run->fresh();
        } finally {
            $this->cleanupWorkDirectory($workDir);
        }
    }

    public function availablePackageDocuments(Project $project): array
    {
        $project->loadMissing('requisitos');
        $requirements = $project->requisitos
            ->filter(fn ($req) => (bool) ($req->visible ?? true))
            ->values();

        $documents = [];
        foreach ($this->packageSections() as $section) {
            if ($section->match_type === 'studies_subfolders') {
                $studyGroups = $this->orderedStudyGroupsFromRequirements($requirements, $section);
                foreach ($studyGroups as $sequence => $group) {
                    $documents[] = [
                        'key' => $this->studyDocumentKey((string) $group['folder']),
                        'title' => $this->studyDocumentTitle((string) $group['folder'], $sequence + 1),
                        'group' => $section->parent?->name ?: '05 Estudios y Disenos',
                    ];
                }

                continue;
            }

            $documents[] = [
                'key' => $this->sectionDocumentKey($section),
                'title' => (string) $section->name,
                'group' => $section->parent?->name ?: 'Carteras',
            ];
        }

        return $documents;
    }

    private function buildDocumentsManifest(Project $project, string $downloadDir, ?int $userId, AttachmentPackageRun $run): array
    {
        $project->loadMissing('requisitos');

        $visibleRequirements = $project->requisitos
            ->filter(fn ($requirement) => (bool) ($requirement->visible ?? true))
            ->values();

        $evidenceRows = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->get();

        $progressAnalysis = app(RequirementProgressService::class)->analyze($visibleRequirements, $evidenceRows);
        $completedRequirementIds = $this->completedRequirementIds($progressAnalysis);

        $activeRequirements = $visibleRequirements
            ->filter(fn ($requirement) => isset($completedRequirementIds[(int) $requirement->id]))
            ->values();

        $evidences = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->where('in_drive', true)
            ->whereNotNull('drive_file_id')
            ->with('requirement')
            ->get()
            ->filter(fn ($evidence) => $this->isCurrentRequirementEvidenceCandidate($evidence))
            ->sortByDesc(fn ($evidence) => $evidence->id)
            ->groupBy('requirement_id')
            ->map(fn ($items) => $items->first())
            ->values();

        $knownDriveFileIds = $evidences->pluck('drive_file_id')->filter()->map(fn ($id) => (string) $id)->all();
        $evidencesByRequirement = $evidences->groupBy('requirement_id');
        $processedEvidence = 0;
        $downloadErrors = [];
        $manifestTrace = [];
        $documents = [];
        $usedBaseNames = [];
        $usedEvidenceIds = [];
        $selectedKeys = $this->selectedDocumentKeys($run);

        $sections = $this->packageSections();
        $totalEvidence = max(1, $this->estimateSelectedEvidenceDownloads($sections, $selectedKeys, $activeRequirements, $evidences, $evidencesByRequirement));
        if ($sections->isEmpty()) {
            return $this->buildDocumentsManifestLegacy($project, $downloadDir, $userId, $run);
        }

        foreach ($sections as $section) {
            if ($section->match_type === 'studies_subfolders') {
                $studyGroups = $this->orderedStudyGroups($evidences, $section);

                foreach ($studyGroups as $sequence => $group) {
                    $documentKey = $this->studyDocumentKey((string) $group['folder']);
                    if (! $this->shouldGenerateDocument($documentKey, $selectedKeys)) {
                        continue;
                    }
                    $documentTitle = $this->studyDocumentTitle((string) $group['folder'], $sequence + 1);
                    $beforeDocCount = count($documents);
                    $this->appendManifestDocument(
                        $documents,
                        $usedBaseNames,
                        $usedEvidenceIds,
                        $group['items'],
                        $documentTitle,
                        $downloadDir,
                        $userId,
                        $run,
                        $processedEvidence,
                        $totalEvidence,
                        $downloadErrors
                    );

                    $manifestTrace[] = [
                        'document' => $documentTitle,
                        'document_key' => $documentKey,
                        'candidate_requirements_count' => null,
                        'associated_evidences_count' => $group['items']->count(),
                        'included_files_count' => count($documents[$beforeDocCount]['files'] ?? []),
                        'loose_files_added_count' => 0,
                    ];
                }

                continue;
            }

            $documentKey = $this->sectionDocumentKey($section);
            if (! $this->shouldGenerateDocument($documentKey, $selectedKeys)) {
                continue;
            }

            $candidateRequirements = $activeRequirements
                ->filter(fn ($requirement) => $this->matchesPackageSection($section, $requirement))
                ->sortBy(fn ($requirement) => $this->requirementSortKey($requirement))
                ->values();
            $activeDocumentCodes = $this->activeRequirementDocumentCodes($candidateRequirements);

            $recursiveSourceFolders = $this->recursiveSourceFolders($section);
            $directRequirements = $this->requirementsOutsideRecursiveFolders($candidateRequirements, $recursiveSourceFolders);

            $items = $this->evidencesForRequirements(
                $directRequirements,
                $evidencesByRequirement,
                $usedEvidenceIds,
                fn ($evidence): bool => ! $this->isEditableEvidence($evidence)
            );

            $docIndex = null;
            if ($items->isNotEmpty()) {
                $docIndex = $this->appendManifestDocument(
                    $documents,
                    $usedBaseNames,
                    $usedEvidenceIds,
                    $items,
                    (string) $section->name,
                    $downloadDir,
                    $userId,
                    $run,
                    $processedEvidence,
                    $totalEvidence,
                    $downloadErrors
                );
            } elseif (
                $section->include_all_folder_files
                || $this->isFormulationCodePrefixSection($section)
                || $recursiveSourceFolders->isNotEmpty()
            ) {
                $docIndex = $this->ensureEmptyManifestDocument($documents, $usedBaseNames, (string) $section->name, (string) $section->source_group_code, (string) $section->source_folder);
            }

            $looseFilesAdded = 0;
            if ($section->include_all_folder_files && $docIndex !== null) {
                $looseFilesAdded += $this->appendConfiguredFolderFiles($project, $section, $downloadDir, $userId, $documents, $docIndex, $knownDriveFileIds, $downloadErrors, $run, $activeDocumentCodes);
            }

            if ($this->isFormulationCodePrefixSection($section) && $docIndex !== null) {
                $looseFilesAdded += $this->appendFormulationLooseFiles($project, $section, $downloadDir, $userId, $documents, $docIndex, $knownDriveFileIds, $downloadErrors, $run);
            }

            $recursiveBundleCount = 0;
            $recursiveBundleTrace = [];
            $recursiveBundleResult = ['root_remainder_added' => 0];
            if ($recursiveSourceFolders->isNotEmpty() && $docIndex !== null) {
                $recursiveBundleResult = $this->appendRecursiveSubfolderBundles(
                    $project,
                    $section,
                    $candidateRequirements,
                    $evidencesByRequirement,
                    $downloadDir,
                    $userId,
                    $documents,
                    $docIndex,
                    $usedEvidenceIds,
                    $downloadErrors,
                    $run,
                    $processedEvidence,
                    $totalEvidence,
                    $activeDocumentCodes
                );
                $recursiveBundleCount = (int) ($recursiveBundleResult['added'] ?? 0);
                $recursiveBundleTrace = $recursiveBundleResult['bundles'] ?? [];
            }

            $manifestTrace[] = [
                'document' => (string) $section->name,
                'document_key' => $documentKey,
                'candidate_requirements_count' => $candidateRequirements->count(),
                'candidate_requirement_ids' => $candidateRequirements->pluck('id')->values()->all(),
                'associated_evidences_count' => $items->count(),
                'included_files_count' => $docIndex !== null ? count($documents[$docIndex]['files'] ?? []) : 0,
                'loose_files_added_count' => $looseFilesAdded,
                'recursive_files_added_count' => $recursiveBundleCount,
                'recursive_resolved_folders' => collect($recursiveBundleTrace)
                    ->pluck('resolved_folders')
                    ->flatten(1)
                    ->values()
                    ->all(),
                'recursive_missing_folders' => collect($recursiveBundleTrace)
                    ->pluck('missing_folders')
                    ->flatten(1)
                    ->unique()
                    ->values()
                    ->all(),
                'recursive_bundles' => $recursiveBundleTrace,
                'recursive_root_remainder_added_count' => (int) ($recursiveBundleResult['root_remainder_added'] ?? 0),
            ];
        }

        // En el flujo parametrizado no se crean carteras fallback con evidencias
        // sobrantes: toda cartera generada debe existir en attachment_package_sections.

        if (! empty($downloadErrors) || ! empty($manifestTrace)) {
            $meta = is_array($run->meta) ? $run->meta : [];
            $meta['manifest_trace'] = $manifestTrace;
            $meta['download_errors'] = $downloadErrors;
            $meta['download_errors_count'] = count($downloadErrors);
            $meta['heartbeat_at'] = now()->toDateTimeString();
            $run->forceFill(['meta' => $meta])->save();
        }

        return $documents;
    }

    private function estimateSelectedEvidenceDownloads($sections, array $selectedKeys, $activeRequirements, $evidences, $evidencesByRequirement): int
    {
        $total = 0;

        foreach ($sections as $section) {
            if ($section->match_type === 'studies_subfolders') {
                foreach ($this->orderedStudyGroups($evidences, $section) as $group) {
                    $documentKey = $this->studyDocumentKey((string) $group['folder']);
                    if ($this->shouldGenerateDocument($documentKey, $selectedKeys)) {
                        $total += $group['items']->count();
                    }
                }

                continue;
            }

            $documentKey = $this->sectionDocumentKey($section);
            if (! $this->shouldGenerateDocument($documentKey, $selectedKeys)) {
                continue;
            }

            $candidateRequirements = $activeRequirements
                ->filter(fn ($requirement) => $this->matchesPackageSection($section, $requirement))
                ->values();

            $recursiveSourceFolders = $this->recursiveSourceFolders($section);
            $total += $this->evidencesForRequirements(
                $candidateRequirements,
                $evidencesByRequirement,
                []
            )->count();
        }

        return $total;
    }

    private function packageSections()
    {
        return AttachmentPackageSection::query()
            ->with('parent')
            ->where('active', true)
            ->whereNotNull('parent_id')
            ->whereHas('parent', fn ($query) => $query->where('active', true))
            ->get()
            ->sort(function ($a, $b) {
                $parentOrder = ($a->parent?->orden ?? 0) <=> ($b->parent?->orden ?? 0);
                if ($parentOrder !== 0) {
                    return $parentOrder;
                }

                $sectionOrder = ((int) $a->orden) <=> ((int) $b->orden);
                if ($sectionOrder !== 0) {
                    return $sectionOrder;
                }

                return strnatcasecmp((string) $a->name, (string) $b->name);
            })
            ->values();
    }

    private function appendRecursiveSubfolderBundles(
        Project $project,
        AttachmentPackageSection $section,
        $candidateRequirements,
        $evidencesByRequirement,
        string $downloadDir,
        ?int $userId,
        array &$documents,
        int $docIndex,
        array &$usedEvidenceIds,
        array &$downloadErrors,
        AttachmentPackageRun $run,
        int &$processedEvidence,
        int $totalEvidence,
        array $activeDocumentCodes = []
    ): array {
        $sourceFolders = $this->recursiveSourceFolders($section);
        if ($sourceFolders->isEmpty()) {
            return ['added' => 0, 'bundles' => []];
        }

        $docDir = $downloadDir.'/'.$documents[$docIndex]['base_name'];
        File::ensureDirectoryExists($docDir);
        $parentUsedNames = collect($documents[$docIndex]['files'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->flip()
            ->all();
        $seenDriveFileIds = collect($documents[$docIndex]['files'] ?? [])
            ->pluck('drive_file_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->all();

        $bundles = [];
        $added = 0;

        foreach ($sourceFolders as $sourceFolder) {
            $normalizedFolder = $this->normalizeFolderLabel($sourceFolder);
            $folderRequirements = $candidateRequirements
                ->filter(fn ($requirement) => $this->normalizeFolderLabel((string) ($requirement->carpeta ?? '')) === $normalizedFolder)
                ->values();

            $canCreateBundle = $folderRequirements->isNotEmpty();

            $bundleFiles = [];
            $bundleUsedNames = [];
            $bundleDir = $docDir.'/bundle_sources/'.$this->sanitizeFileBase($sourceFolder);
            File::ensureDirectoryExists($bundleDir);

            $linkedItems = $canCreateBundle
                ? $this->evidencesForRequirements(
                    $folderRequirements,
                    $evidencesByRequirement,
                    $usedEvidenceIds,
                    fn ($evidence): bool => ! $this->isEditableEvidence($evidence)
                )
                : collect();
            foreach ($linkedItems as $index => $evidence) {
                $usedEvidenceIds[$evidence->id] = true;
                $fileId = (string) $evidence->drive_file_id;
                $original = (string) ($evidence->drive_file_name ?: ('archivo_'.$index.'.pdf'));
                $displayName = $this->evidenceDisplayName($evidence, $original);
                $safeName = $this->uniqueFileName($this->sanitizeFileName($displayName), $bundleUsedNames);
                $localPath = $bundleDir.'/'.$safeName;

                try {
                    $this->touchDownloadStage(
                        $run,
                        $original,
                        $fileId,
                        $processedEvidence + 1,
                        $totalEvidence
                    );
                    $this->drive()->downloadFile($fileId, $localPath, $userId);
                    $bundleFiles[] = [
                        'name' => $safeName,
                        'path' => $localPath,
                        'sort_key' => $this->manifestEntrySortKey($evidence->requirement, $safeName),
                        'drive_file_id' => $fileId,
                        'folder_name' => $sourceFolder,
                        'attachment_name' => $this->attachmentPathName($sourceFolder, $safeName),
                    ];
                    if ($fileId !== '') {
                        $seenDriveFileIds[$fileId] = true;
                    }
                } catch (\Throwable $e) {
                    $downloadErrors[] = [
                        'file_id' => $fileId,
                        'file_name' => $original,
                        'error' => $e->getMessage(),
                    ];
                } finally {
                    $processedEvidence++;
                    $detailPercent = (int) round(($processedEvidence / $totalEvidence) * 100);
                    $overallPercent = 40 + (int) round(($processedEvidence / $totalEvidence) * 20);
                    $this->touchStage(
                        $run,
                        'Descargando evidencias desde Drive ('.$processedEvidence.'/'.$totalEvidence.')',
                        $overallPercent,
                        $detailPercent
                    );
                }
            }

            // La fuente de verdad son las evidencias vigentes de requisitos OK.
            // No rastreamos PDFs sueltos de Drive dentro de la subcarpeta porque
            // pueden existir archivos no asociados a requisitos de la plataforma.
            $recursiveResult = ['added' => 0, 'parent_added' => 0, 'resolved_folders' => [], 'missing_folders' => []];

            $bundleFiles = collect($bundleFiles)
                ->sortBy(fn (array $file) => (string) ($file['sort_key'] ?? $this->fileSortKey((string) ($file['name'] ?? ''))))
                ->values()
                ->all();

            if (! empty($bundleFiles)) {
                $bundleAttachmentName = $this->uniqueFileName($sourceFolder.'.pdf', $parentUsedNames);
                $documents[$docIndex]['files'][] = [
                    'name' => $bundleAttachmentName,
                    'bundle_title' => $sourceFolder,
                    'bundle_files' => $bundleFiles,
                    'sort_key' => $this->bundleSortKey($sourceFolder),
                    'folder_name' => $sourceFolder,
                    'attachment_name' => $bundleAttachmentName,
                ];
                $added++;
            }

            $bundles[] = [
                'folder' => $sourceFolder,
                'linked_evidences_count' => $linkedItems->count(),
                'bundle_files_count' => count($bundleFiles),
                'recursive_files_added_count' => (int) ($recursiveResult['added'] ?? 0),
                'recursive_parent_files_added_count' => (int) ($recursiveResult['parent_added'] ?? 0),
                'resolved_folders' => $recursiveResult['resolved_folders'] ?? [],
                'missing_folders' => $recursiveResult['missing_folders'] ?? [],
            ];
        }

        // Las carteras se arman desde requisitos OK. No importamos archivos
        // sueltos de la raíz de Drive porque pueden existir allí sin estar
        // vinculados a un requisito activo/completado en la plataforma.
        $rootRemainderResult = ['added' => 0, 'resolved_folders' => [], 'missing_folders' => []];

        $this->sortManifestDocumentFiles($documents, $docIndex);

        return [
            'added' => $added,
            'bundles' => $bundles,
            'root_remainder_added' => (int) ($rootRemainderResult['added'] ?? 0),
            'root_remainder_resolved_folders' => $rootRemainderResult['resolved_folders'] ?? [],
            'root_remainder_missing_folders' => $rootRemainderResult['missing_folders'] ?? [],
        ];
    }

    private function appendRecursiveRootRemainderFiles(
        Project $project,
        AttachmentPackageSection $section,
        string $docDir,
        ?int $userId,
        array &$documents,
        int $docIndex,
        array &$parentUsedNames,
        array &$seenDriveFileIds,
        array &$downloadErrors,
        AttachmentPackageRun $run,
        array $activeDocumentCodes = []
    ): array {
        $rootFolder = trim((string) ($section->recursive_root_folder ?: $section->source_folder ?: ''));
        if ($rootFolder === '') {
            return ['added' => 0, 'resolved_folders' => [], 'missing_folders' => []];
        }

        try {
            $result = $this->drive()->listProjectSubfolderFiles($project, $rootFolder, [], $userId, null);
        } catch (\Throwable $e) {
            $downloadErrors[] = [
                'file_id' => null,
                'file_name' => $rootFolder,
                'error' => 'No se pudo listar la carpeta raíz de presupuesto: '.$e->getMessage(),
            ];

            return ['added' => 0, 'resolved_folders' => [], 'missing_folders' => [$rootFolder]];
        }

        $added = 0;
        foreach (($result['items'] ?? collect())->values() as $index => $file) {
            $fileId = (string) ($file['id'] ?? '');
            $original = (string) ($file['name'] ?? ('archivo_'.$index));
            if ($fileId === '' || isset($seenDriveFileIds[$fileId])) {
                continue;
            }
            if (! $this->matchesActiveDocumentCode($original, $activeDocumentCodes)) {
                continue;
            }

            $safeName = $this->uniqueFileName($this->sanitizeFileName($original), $parentUsedNames);
            $localPath = $docDir.'/'.$safeName;

            try {
                $this->touchDriveFileStage($run, 'Descargando archivo desde carpeta Drive', $original, $fileId);
                $this->drive()->downloadFile($fileId, $localPath, $userId);
                $folderName = (string) ($section->source_folder ?: $section->name);
                $documents[$docIndex]['files'][] = [
                    'name' => $safeName,
                    'path' => $localPath,
                    'sort_key' => $this->fileSortKey($safeName),
                    'drive_file_id' => $fileId,
                    'folder_name' => $folderName,
                    'attachment_name' => $this->attachmentPathName($folderName, $safeName),
                ];
                $seenDriveFileIds[$fileId] = true;
                $added++;
            } catch (\Throwable $e) {
                $downloadErrors[] = [
                    'file_id' => $fileId,
                    'file_name' => $original,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'added' => $added,
            'resolved_folders' => $result['resolved_folders'] ?? [],
            'missing_folders' => $result['missing_folders'] ?? [],
        ];
    }

    private function appendManifestDocument(
        array &$documents,
        array &$usedBaseNames,
        array &$usedEvidenceIds,
        $items,
        string $documentTitle,
        string $downloadDir,
        ?int $userId,
        AttachmentPackageRun $run,
        int &$processedEvidence,
        int $totalEvidence,
        array &$downloadErrors
    ): int {
        $safeBaseName = $this->uniqueBaseName($documentTitle, '00', $documentTitle, $usedBaseNames);
        $docDir = $downloadDir.'/'.$safeBaseName;
        File::ensureDirectoryExists($docDir);

        $files = [];
        $usedNames = [];
        foreach ($items as $index => $evidence) {
            $usedEvidenceIds[$evidence->id] = true;
            $original = (string) ($evidence->drive_file_name ?: ('archivo_'.$index.'.pdf'));
            $displayName = $this->evidenceDisplayName($evidence, $original);
            $safeName = $this->uniqueFileName($this->sanitizeFileName($displayName), $usedNames);
            $localPath = $docDir.'/'.$safeName;
            try {
                $this->touchDownloadStage(
                    $run,
                    $original,
                    (string) $evidence->drive_file_id,
                    $processedEvidence + 1,
                    $totalEvidence
                );
                $this->drive()->downloadFile($evidence->drive_file_id, $localPath, $userId);
                $folderName = trim((string) ($evidence->requirement?->carpeta ?? ''));
                if ($folderName === '') {
                    $folderName = $documentTitle;
                }
                $files[] = [
                    'name' => $safeName,
                    'path' => $localPath,
                    'sort_key' => $this->manifestEntrySortKey($evidence->requirement, $safeName),
                    'drive_file_id' => (string) $evidence->drive_file_id,
                    'folder_name' => $folderName,
                    'attachment_name' => $this->attachmentPathName($folderName, $safeName),
                ];
            } catch (\Throwable $e) {
                $downloadErrors[] = [
                    'file_id' => $evidence->drive_file_id,
                    'file_name' => $original,
                    'error' => $e->getMessage(),
                ];
            } finally {
                $processedEvidence++;
                $detailPercent = (int) round(($processedEvidence / $totalEvidence) * 100);
                $overallPercent = 40 + (int) round(($processedEvidence / $totalEvidence) * 20);
                $this->touchStage(
                    $run,
                    'Descargando evidencias desde Drive ('.$processedEvidence.'/'.$totalEvidence.')',
                    $overallPercent,
                    $detailPercent
                );
            }
        }

        $documents[] = [
            'key' => $this->documentKeyFromTitle($documentTitle),
            'title' => $documentTitle,
            'base_name' => $safeBaseName,
            'files' => $files,
        ];

        return count($documents) - 1;
    }

    private function ensureEmptyManifestDocument(array &$documents, array &$usedBaseNames, string $documentTitle, string $groupCode, string $subgroup): int
    {
        $safeBaseName = $this->uniqueBaseName($documentTitle, $groupCode, $subgroup, $usedBaseNames);
        $documents[] = [
            'key' => $this->documentKeyFromTitle($documentTitle),
            'title' => $documentTitle,
            'base_name' => $safeBaseName,
            'files' => [],
        ];

        return count($documents) - 1;
    }

    private function normalizeFolderLabel(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9.]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function matchesPackageSection(AttachmentPackageSection $section, $requirement): bool
    {
        if (! $requirement) {
            return false;
        }

        $code = trim((string) ($requirement->codigo_interno ?? $requirement->numeracion ?? ''));
        $groupCode = str_pad((string) $this->extractGroupCode($code), 2, '0', STR_PAD_LEFT);
        $folder = (string) ($requirement->carpeta ?? '');
        $folderGroupCode = $this->detectTopGroupCode($folder);

        return match ($section->match_type) {
            'group_code' => ($folderGroupCode ?: $groupCode) === str_pad((string) $section->source_group_code, 2, '0', STR_PAD_LEFT),
            'folder' => $this->normalizeFolderLabel($folder) === $this->normalizeFolderLabel((string) $section->source_folder),
            'code_prefix' => $this->matchesAnyCodePrefix($code, $this->sectionCodePrefixes($section)),
            'studies_subfolders' => $groupCode === str_pad((string) ($section->source_group_code ?: '05'), 2, '0', STR_PAD_LEFT),
            default => false,
        };
    }

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*0?([1-5])(?:\D|$)/', $folder, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }

        $normalized = $this->normalizeFolderLabel($folder);
        if (str_contains($normalized, 'formulacion')) {
            return '01';
        }
        if (str_contains($normalized, 'presupuesto')) {
            return '02';
        }
        if (str_contains($normalized, 'certificacion')) {
            return '03';
        }
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) {
            return '04';
        }
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) {
            return '05';
        }

        return null;
    }

    private function matchesAnyCodePrefix(string $code, array $prefixes): bool
    {
        $code = trim($code);
        foreach ($prefixes as $prefix) {
            $prefix = trim((string) $prefix);
            if ($prefix === '') {
                continue;
            }
            if ($code === $prefix || Str::startsWith($code, $prefix.' ')) {
                return true;
            }
        }

        return false;
    }

    private function appendConfiguredFolderFiles(
        Project $project,
        AttachmentPackageSection $section,
        string $downloadDir,
        ?int $userId,
        array &$documents,
        int $docIndex,
        array $knownDriveFileIds,
        array &$downloadErrors,
        AttachmentPackageRun $run,
        array $activeDocumentCodes = []
    ): int {
        $requirement = $project->requisitos->first(function ($req) use ($section) {
            return $this->normalizeFolderLabel((string) ($req->carpeta ?? '')) === $this->normalizeFolderLabel((string) $section->source_folder);
        });

        if (! $requirement) {
            return 0;
        }

        try {
            $result = $this->drive()->listRequirementFiles($project, $requirement, $userId, null, null);
        } catch (\Throwable $e) {
            $downloadErrors[] = [
                'file_id' => null,
                'file_name' => (string) $section->source_folder,
                'error' => 'No se pudo listar la carpeta completa: '.$e->getMessage(),
            ];

            return 0;
        }

        $known = array_fill_keys($knownDriveFileIds, true);
        $allowed = collect($section->allowed_extensions ?? [])
            ->map(fn ($extension) => mb_strtolower(ltrim((string) $extension, '.')))
            ->filter()
            ->values()
            ->all();

        $docDir = $downloadDir.'/'.$documents[$docIndex]['base_name'];
        File::ensureDirectoryExists($docDir);
        $usedNames = collect($documents[$docIndex]['files'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->flip()
            ->all();

        $added = 0;
        foreach (($result['items'] ?? []) as $index => $file) {
            $fileId = (string) ($file['id'] ?? '');
            $original = (string) ($file['name'] ?? ('archivo_'.$index));
            if ($fileId === '' || isset($known[$fileId])) {
                continue;
            }
            if (! $this->matchesActiveDocumentCode($original, $activeDocumentCodes)) {
                continue;
            }
            $extension = mb_strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (! empty($allowed) && ! in_array($extension, $allowed, true)) {
                continue;
            }

            $safeName = $this->uniqueFileName($this->sanitizeFileName($original), $usedNames);
            $localPath = $docDir.'/'.$safeName;
            try {
                $this->touchDriveFileStage($run, 'Descargando archivo desde carpeta configurada', $original, $fileId);
                $this->drive()->downloadFile($fileId, $localPath, $userId);
                $folderName = '01 Formulacion';
                $documents[$docIndex]['files'][] = [
                    'name' => $safeName,
                    'path' => $localPath,
                    'sort_key' => $this->fileSortKey($safeName),
                    'drive_file_id' => $fileId,
                    'folder_name' => $folderName,
                    'attachment_name' => $this->attachmentPathName($folderName, $safeName),
                ];
                $added++;
            } catch (\Throwable $e) {
                $downloadErrors[] = [
                    'file_id' => $fileId,
                    'file_name' => $original,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->sortManifestDocumentFiles($documents, $docIndex);

        return $added;
    }

    private function collectRecursiveSubfolderFiles(
        Project $project,
        AttachmentPackageSection $section,
        string $targetDir,
        ?int $userId,
        array &$targetFiles,
        array &$usedNames,
        array &$documents,
        int $docIndex,
        array &$parentUsedNames,
        array &$seenDriveFileIds,
        array &$downloadErrors,
        ?array $sourceFolders = null,
        ?AttachmentPackageRun $run = null,
        array $activeDocumentCodes = []
    ): array {
        $rootFolder = trim((string) ($section->recursive_root_folder ?: $section->source_folder ?: ''));
        $sourceFolders = collect($sourceFolders ?? ($section->recursive_source_folders ?? []))
            ->map(fn ($folder) => trim((string) $folder))
            ->filter()
            ->values()
            ->all();

        if ($rootFolder === '' || empty($sourceFolders)) {
            return [
                'added' => 0,
                'resolved_folders' => [],
                'missing_folders' => $sourceFolders,
            ];
        }

        try {
            $result = $this->drive()->listProjectSubfolderFiles(
                $project,
                $rootFolder,
                $sourceFolders,
                $userId,
                null
            );
        } catch (\Throwable $e) {
            $downloadErrors[] = [
                'file_id' => null,
                'file_name' => $rootFolder,
                'error' => 'No se pudo listar recursivamente las subcarpetas configuradas: '.$e->getMessage(),
            ];

            return [
                'added' => 0,
                'resolved_folders' => [],
                'missing_folders' => $sourceFolders,
            ];
        }

        $bundleExtensions = collect($section->allowed_extensions ?? [])
            ->map(fn ($extension) => mb_strtolower(ltrim((string) $extension, '.')))
            ->filter()
            ->values()
            ->all();
        if (empty($bundleExtensions)) {
            $bundleExtensions = ['pdf'];
        }

        File::ensureDirectoryExists($targetDir);

        $added = 0;
        $parentAdded = 0;
        foreach (($result['items'] ?? collect())->values() as $index => $file) {
            $fileId = (string) ($file['id'] ?? '');
            $original = (string) ($file['name'] ?? ('archivo_'.$index));
            if ($fileId === '' || isset($seenDriveFileIds[$fileId])) {
                continue;
            }

            $extension = mb_strtolower(pathinfo($original, PATHINFO_EXTENSION));
            $isBundleFile = in_array($extension, $bundleExtensions, true);
            if (! $isBundleFile) {
                continue;
            }

            $safeName = $this->uniqueFileName($this->sanitizeFileName($original), $usedNames);
            $localPath = $targetDir.'/'.$safeName;

            try {
                if ($run) {
                    $this->touchDriveFileStage($run, 'Descargando PDF desde subcarpeta Drive', $original, $fileId);
                }
                $this->drive()->downloadFile($fileId, $localPath, $userId);
                $targetFiles[] = [
                    'name' => $safeName,
                    'path' => $localPath,
                    'sort_key' => $this->fileSortKey($safeName),
                    'drive_file_id' => $fileId,
                ];
                $added++;
                $seenDriveFileIds[$fileId] = true;
            } catch (\Throwable $e) {
                $downloadErrors[] = [
                    'file_id' => $fileId,
                    'file_name' => $original,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'added' => $added,
            'parent_added' => $parentAdded,
            'resolved_folders' => $result['resolved_folders'] ?? [],
            'missing_folders' => $result['missing_folders'] ?? [],
        ];
    }

    private function appendFormulationLooseFiles(
        Project $project,
        AttachmentPackageSection $section,
        string $downloadDir,
        ?int $userId,
        array &$documents,
        int $docIndex,
        array $knownDriveFileIds,
        array &$downloadErrors,
        AttachmentPackageRun $run
    ): int {
        $requirement = $project->requisitos->first(function ($req) {
            return $this->normalizeFolderLabel((string) ($req->carpeta ?? '')) === '01 formulacion';
        });

        if (! $requirement) {
            return 0;
        }

        try {
            $result = $this->drive()->listDirectRequirementFiles($project, $requirement, $userId);
        } catch (\Throwable $e) {
            $downloadErrors[] = [
                'file_id' => null,
                'file_name' => (string) $section->name,
                'error' => 'No se pudo listar archivos directos de Formulación: '.$e->getMessage(),
            ];

            return 0;
        }

        $known = array_fill_keys($knownDriveFileIds, true);
        $docDir = $downloadDir.'/'.$documents[$docIndex]['base_name'];
        File::ensureDirectoryExists($docDir);
        $usedNames = collect($documents[$docIndex]['files'] ?? [])
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower((string) $name))
            ->flip()
            ->all();

        $added = 0;
        $codes = $this->formulationLooseCodesForSection($section);
        $onlyMissingCodes = ! $this->isFormulationOtherSupportsSection($section);
        $representedCodes = $onlyMissingCodes
            ? $this->representedDocumentCodes($documents[$docIndex]['files'] ?? [])
            : [];

        $items = collect($result['items'] ?? [])
            ->filter(fn (array $file) => $this->startsWithAnyDocumentCode((string) ($file['name'] ?? ''), $codes))
            ->sortBy(fn (array $file) => $this->fileSortKey((string) ($file['name'] ?? '')))
            ->values();

        foreach ($items as $index => $file) {
            $fileId = (string) ($file['id'] ?? '');
            $original = (string) ($file['name'] ?? ('archivo_'.$index));
            if ($fileId === '' || isset($known[$fileId])) {
                continue;
            }

            $documentCode = $this->firstDocumentCodeForMatch($original);
            if ($onlyMissingCodes && $documentCode !== '' && isset($representedCodes[$documentCode])) {
                continue;
            }

            $safeName = $this->uniqueFileName($this->sanitizeFileName($original), $usedNames);
            $localPath = $docDir.'/'.$safeName;
            try {
                $this->touchDriveFileStage($run, 'Descargando archivo directo de Formulación', $original, $fileId);
                $this->drive()->downloadFile($fileId, $localPath, $userId);
                $documents[$docIndex]['files'][] = [
                    'name' => $safeName,
                    'path' => $localPath,
                    'sort_key' => $this->fileSortKey($safeName),
                    'drive_file_id' => $fileId,
                ];
                $known[$fileId] = true;
                if ($documentCode !== '') {
                    $representedCodes[$documentCode] = true;
                }
                $added++;
            } catch (\Throwable $e) {
                $downloadErrors[] = [
                    'file_id' => $fileId,
                    'file_name' => $original,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->sortManifestDocumentFiles($documents, $docIndex);

        return $added;
    }

    private function evidencesForRequirements($requirements, $evidencesByRequirement, array $usedEvidenceIds, ?callable $filter = null)
    {
        return collect($requirements)
            ->flatMap(function ($requirement) use ($evidencesByRequirement, $usedEvidenceIds) {
                return collect($evidencesByRequirement->get($requirement->id, collect()))
                    ->filter(fn ($evidence) => ! isset($usedEvidenceIds[$evidence->id]));
            })
            ->when($filter !== null, fn ($items) => $items->filter($filter))
            ->sortBy(fn ($evidence) => $this->requirementSortKey($evidence->requirement).'|'.$this->fileSortKey((string) ($evidence->drive_file_name ?? '')))
            ->values();
    }

    private function isCurrentRequirementEvidenceCandidate($evidence): bool
    {
        if (! $evidence->requirement || ! (bool) ($evidence->in_drive ?? false)) {
            return false;
        }

        if ($evidence->requirement->requiresLicensePermitClassification()
            && ! RequirementEvidence::isValidLicensePermitStatus($evidence->license_permit_status)) {
            return false;
        }

        if (blank($evidence->drive_file_id)) {
            return false;
        }

        $evidenceFolder = trim((string) ($evidence->drive_folder_name ?? ''));
        $requirementFolder = trim((string) ($evidence->requirement?->carpeta ?? ''));

        if ($evidenceFolder !== '' && $requirementFolder !== '') {
            return $evidenceFolder === $requirementFolder;
        }

        return true;
    }

    private function evidenceDeduplicationKey($evidence): string
    {
        $requirementId = (string) ($evidence->requirement_id ?? '');
        $name = Str::ascii((string) ($evidence->drive_file_name ?? ''));
        $name = mb_strtolower($name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return $requirementId.'|'.$name;
    }

    private function isPdfEvidence($evidence): bool
    {
        $name = mb_strtolower((string) ($evidence->drive_file_name ?? ''));
        if (str_ends_with($name, '.pdf')) {
            return true;
        }

        return mb_strtolower((string) ($evidence->drive_mime_type ?? '')) === 'application/pdf';
    }

    private function requirementSortKey($requirement): string
    {
        $code = (string) ($requirement->orden ?: $requirement->codigo_interno ?: $requirement->numeracion ?: '999');
        $title = (string) ($requirement->nombre_documento ?: $requirement->requisito ?: '');

        return $this->requirementCodeSortKey($code, $title);
    }

    private function requirementCodeSortKey(string $code, string $title = ''): string
    {
        preg_match_all('/\d+/', $code, $matches);
        $segments = collect($matches[0] ?? [])
            ->map(fn ($segment) => str_pad((string) $segment, 8, '0', STR_PAD_LEFT))
            ->values()
            ->all();

        if (empty($segments)) {
            $segments = ['99999999'];
        }

        return implode('.', $segments).'.00000000 '.$this->fileSortKey($title);
    }

    private function fileSortKey(string $name): string
    {
        $normalized = Str::ascii($name);
        $normalized = mb_strtolower($normalized);
        $normalized = str_replace(['.', '_'], ' ', $normalized);
        $normalized = preg_replace_callback('/\d+/', fn ($match) => str_pad($match[0], 8, '0', STR_PAD_LEFT), $normalized);

        return (string) $normalized;
    }

    private function isFormulationCodePrefixSection(AttachmentPackageSection $section): bool
    {
        return $section->match_type === 'code_prefix'
            && $this->normalizeFolderLabel((string) ($section->parent?->name ?? '')) === '01 formulacion';
    }

    private function isFormulationOtherSupportsSection(AttachmentPackageSection $section): bool
    {
        return $this->normalizeFolderLabel((string) $section->name) === '1 formulacion 3 otros soportes ct';
    }

    private function hasRecursiveSubfolderSources(AttachmentPackageSection $section): bool
    {
        return $this->recursiveSourceFolders($section)->isNotEmpty();
    }

    private function recursiveSourceFolders(AttachmentPackageSection $section)
    {
        $folders = $section->recursive_source_folders ?? [];
        if (is_string($folders)) {
            $folders = preg_split('/[,;\n]+/', $folders) ?: [];
        }

        return collect($folders)
            ->flatMap(function ($folder): array {
                if (is_string($folder) && str_contains($folder, ',')) {
                    return preg_split('/[,;\n]+/', $folder) ?: [];
                }

                return [$folder];
            })
            ->map(fn ($folder) => trim((string) $folder))
            ->filter(fn ($folder) => ! $this->isEditableFolderLabel((string) $folder))
            ->unique(fn ($folder) => $this->normalizeFolderLabel((string) $folder))
            ->values()
            ->mapWithKeys(fn ($folder) => [$this->normalizeFolderLabel((string) $folder) => (string) $folder]);
    }

    private function requirementsOutsideRecursiveFolders($requirements, $recursiveSourceFolders)
    {
        $recursiveKeys = collect($recursiveSourceFolders)->keys()->flip();
        if ($recursiveKeys->isEmpty()) {
            return $requirements;
        }

        return collect($requirements)
            ->reject(fn ($requirement) => $recursiveKeys->has($this->normalizeFolderLabel((string) ($requirement->carpeta ?? ''))))
            ->values();
    }

    private function isEditableEvidence($evidence): bool
    {
        return $this->isEditableFolderLabel((string) ($evidence->requirement?->carpeta ?? ''))
            || $this->isEditableFolderLabel((string) ($evidence->drive_folder_name ?? ''));
    }

    private function isEditableFolderLabel(string $folder): bool
    {
        $normalized = $this->normalizeFolderLabel($folder);
        if ($normalized === '') {
            return false;
        }

        foreach (preg_split('/[\/\\>]+/', $folder) ?: [] as $segment) {
            $segment = $this->normalizeFolderLabel((string) $segment);
            if ($segment === 'editable' || $segment === 'editables') {
                return true;
            }
        }

        return preg_match('/(^|\s)editables?(\s|$)/', $normalized) === 1;
    }

    private function preferredRecursiveExtension(AttachmentPackageSection $section): ?string
    {
        $extensions = collect($section->allowed_extensions ?? [])
            ->map(fn ($extension) => mb_strtolower(ltrim((string) $extension, '.')))
            ->filter()
            ->values();

        if ($extensions->count() === 1) {
            return (string) $extensions->first();
        }

        return null;
    }

    private function formulationLooseCodesForSection(AttachmentPackageSection $section): array
    {
        if ($this->normalizeFolderLabel((string) $section->name) === '1 formulacion 3 otros soportes ct') {
            return ['1.07', '1.08', '1.09', '1.10', '1.11', '1.12', '1.13'];
        }

        return $this->sectionCodePrefixes($section);
    }

    private function sectionCodePrefixes(AttachmentPackageSection $section): array
    {
        $prefixes = $section->code_prefixes ?? [];
        if (is_string($prefixes)) {
            $prefixes = preg_split('/[,;\n]+/', $prefixes) ?: [];
        }

        return collect($prefixes)
            ->map(fn ($code) => trim((string) $code, " \t\n\r\0\x0B\"'"))
            ->filter()
            ->values()
            ->all();
    }

    private function startsWithAnyDocumentCode(string $name, array $codes): bool
    {
        $normalized = $this->documentCodeMatchLabel(pathinfo($name, PATHINFO_FILENAME));
        foreach ($codes as $code) {
            $code = $this->documentCodeMatchLabel($code);
            if ($normalized === $code || Str::startsWith($normalized, $code.' ')) {
                return true;
            }
        }

        return false;
    }

    private function requirementRepresentsRecursiveBundle($requirement, string $sourceFolder): bool
    {
        $source = $this->normalizeFolderLabel($sourceFolder);
        $sourceCode = null;
        if (preg_match('/^\d+(?:\.\d+)*/', $source, $matches)) {
            $sourceCode = $matches[0];
        }

        if ($sourceCode) {
            foreach ([$requirement->orden ?? null, $requirement->codigo_interno ?? null, $requirement->numeracion ?? null] as $code) {
                if ($this->normalizeFolderLabel((string) $code) === $sourceCode) {
                    return true;
                }
            }
        }

        $label = $this->normalizeFolderLabel(trim(implode(' ', [
            (string) ($requirement->nombre_documento ?? ''),
            (string) ($requirement->requisito ?? ''),
            (string) ($requirement->texto ?? ''),
        ])));

        return $label !== '' && $label === $source;
    }

    private function activeRequirementDocumentCodes($requirements): array
    {
        return collect($requirements)
            ->flatMap(function ($requirement): array {
                return [
                    $requirement->orden ?? null,
                    $requirement->codigo_interno ?? null,
                    $requirement->numeracion ?? null,
                ];
            })
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique(fn ($code) => $this->documentCodeMatchLabel((string) $code))
            ->values()
            ->all();
    }

    private function completedRequirementIds(array $progressAnalysis): array
    {
        return collect($progressAnalysis['requirements'] ?? [])
            ->filter(fn (array $status): bool => (bool) ($status['has_evidence'] ?? false)
                && (bool) ($status['count_in_progress'] ?? true))
            ->mapWithKeys(fn (array $status): array => [(int) ($status['id'] ?? 0) => true])
            ->all();
    }

    private function matchesActiveDocumentCode(string $name, array $activeDocumentCodes): bool
    {
        if (empty($activeDocumentCodes)) {
            return true;
        }

        return $this->startsWithAnyDocumentCode($name, $activeDocumentCodes);
    }

    private function representedDocumentCodes(array $files): array
    {
        return collect($files)
            ->map(fn (array $file) => $this->firstDocumentCodeForMatch((string) ($file['name'] ?? '')))
            ->filter()
            ->mapWithKeys(fn (string $code) => [$code => true])
            ->all();
    }

    private function firstDocumentCodeForMatch(string $name): string
    {
        $normalized = $this->documentCodeMatchLabel(pathinfo($name, PATHINFO_FILENAME));
        if (preg_match('/^(\d+) (\d+)/', $normalized, $matches)) {
            return ((int) $matches[1]).' '.str_pad((string) ((int) $matches[2]), 2, '0', STR_PAD_LEFT);
        }

        return '';
    }

    private function documentCodeMatchLabel(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function sortManifestDocumentFiles(array &$documents, int $docIndex): void
    {
        $documents[$docIndex]['files'] = collect($documents[$docIndex]['files'] ?? [])
            ->sortBy(fn (array $file) => (string) ($file['sort_key'] ?? $this->fileSortKey((string) ($file['name'] ?? ''))))
            ->values()
            ->all();
    }

    private function attachmentPathName(string $folderName, string $fileName): string
    {
        $folderName = trim($folderName);
        if ($folderName === '') {
            return $fileName;
        }

        return trim($folderName, '/\\').'/'.$fileName;
    }

    private function evidenceDisplayName($evidence, string $original): string
    {
        $requirement = $evidence->requirement;
        if (! $requirement) {
            return $original;
        }

        $code = trim((string) ($requirement->orden ?: $requirement->codigo_interno ?: $requirement->numeracion ?: ''));
        $title = trim((string) ($requirement->nombre_documento ?: $requirement->requisito ?: ''));
        $extension = pathinfo($original, PATHINFO_EXTENSION);

        $label = $title;
        if ($code !== '' && $title !== '') {
            $normalizedTitle = $this->documentCodeMatchLabel($title);
            $normalizedCode = $this->documentCodeMatchLabel($code);
            if (
                $this->shouldPrefixRequirementCode($code)
                && $normalizedTitle !== $normalizedCode
                && ! str_starts_with($normalizedTitle, $normalizedCode.' ')
            ) {
                $label = trim($code.' '.$title);
            }
        } elseif ($code !== '') {
            $label = $this->shouldPrefixRequirementCode($code) ? $code : $label;
        }

        if ($label === '') {
            $label = pathinfo($original, PATHINFO_FILENAME);
        }

        return $extension !== '' ? $label.'.'.Str::lower($extension) : $label;
    }

    private function manifestEntrySortKey($requirement, string $fallbackName): string
    {
        if (! $requirement) {
            return $this->fileSortKey($fallbackName);
        }

        $code = trim((string) ($requirement->orden ?: $requirement->codigo_interno ?: $requirement->numeracion ?: '999'));
        $title = trim((string) ($requirement->nombre_documento ?: $requirement->requisito ?: $fallbackName));

        return $this->requirementCodeSortKey($code, $title);
    }

    private function bundleSortKey(string $folderLabel): string
    {
        if (preg_match('/^\s*(\d+(?:\.\d+)*)/', $folderLabel, $matches)) {
            return $this->requirementCodeSortKey($matches[1], $folderLabel);
        }

        return $this->fileSortKey($folderLabel);
    }

    private function shouldPrefixRequirementCode(string $code): bool
    {
        $parts = array_values(array_filter(explode('.', trim($code)), fn ($part) => $part !== ''));
        if (count($parts) <= 1) {
            return false;
        }

        $tail = array_slice($parts, 1);
        foreach ($tail as $part) {
            if ((int) $part !== 0) {
                return true;
            }
        }

        return false;
    }

    private function selectedDocumentKeys(AttachmentPackageRun $run): array
    {
        return collect($run->selected_documents ?: [])
            ->map(fn ($key) => (string) $key)
            ->filter()
            ->values()
            ->all();
    }

    private function shouldGenerateDocument(string $key, array $selectedKeys): bool
    {
        return empty($selectedKeys) || in_array($key, $selectedKeys, true);
    }

    private function resolveDocumentTitle(string $groupCode, string $subgroup): string
    {
        $groupCode = trim($groupCode);
        $subgroup = trim($subgroup);

        if ($subgroup !== '') {
            return $subgroup;
        }

        return 'Grupo '.$groupCode;
    }

    private function sectionDocumentKey(AttachmentPackageSection $section): string
    {
        return 'section:'.$section->id;
    }

    private function studyDocumentKey(string $folder): string
    {
        return 'study:'.$this->normalizeFolderLabel($folder);
    }

    private function documentKeyFromTitle(string $title): string
    {
        return 'title:'.$this->normalizeFolderLabel($title);
    }

    private function orderedStudyGroupsFromRequirements($requirements, AttachmentPackageSection $section)
    {
        return collect($requirements)
            ->filter(fn ($requirement) => $this->matchesPackageSection($section, $requirement))
            ->groupBy(fn ($requirement) => trim((string) ($requirement->carpeta ?: 'Sin carpeta')))
            ->map(function ($items, string $folder) {
                $sort = $items
                    ->map(fn ($requirement) => $this->studySortValue((string) ($requirement->orden ?: $requirement->codigo_interno ?: $requirement->numeracion)))
                    ->min();

                return [
                    'folder' => $folder,
                    'sort' => $sort ?? 999999,
                ];
            })
            ->sort(function (array $a, array $b) {
                $sort = ((int) $a['sort']) <=> ((int) $b['sort']);
                if ($sort !== 0) {
                    return $sort;
                }

                return strnatcasecmp((string) $a['folder'], (string) $b['folder']);
            })
            ->values();
    }

    private function orderedStudyGroups($evidences, AttachmentPackageSection $section)
    {
        return $evidences
            ->filter(fn ($evidence) => $this->matchesPackageSection($section, $evidence->requirement))
            ->groupBy(fn ($evidence) => trim((string) ($evidence->requirement->carpeta ?: 'Sin carpeta')))
            ->map(function ($items, string $folder) {
                $sort = $items
                    ->map(fn ($evidence) => $this->studySortValue((string) ($evidence->requirement->orden ?: $evidence->requirement->codigo_interno ?: $evidence->requirement->numeracion)))
                    ->min();

                return [
                    'folder' => $folder,
                    'sort' => $sort ?? 999999,
                    'items' => $items->sortBy(function ($evidence) {
                        return $this->studySortValue((string) ($evidence->requirement->orden ?: $evidence->requirement->codigo_interno ?: $evidence->requirement->numeracion));
                    })->values(),
                ];
            })
            ->sort(function (array $a, array $b) {
                $sort = ((int) $a['sort']) <=> ((int) $b['sort']);
                if ($sort !== 0) {
                    return $sort;
                }

                return strnatcasecmp((string) $a['folder'], (string) $b['folder']);
            })
            ->values();
    }

    private function studySortValue(string $code): int
    {
        if (preg_match('/^\s*5\.(\d+)/', $code, $matches)) {
            $minor = (string) $matches[1];
            $value = (int) $minor;

            // Excel sometimes turns 5.10, 5.20 or 5.30 into 5.1, 5.2, 5.3.
            if (strlen($minor) === 1) {
                $value *= 10;
            }

            return $value;
        }

        return 999999;
    }

    private function studyDocumentTitle(string $folder, int $sequence): string
    {
        return sprintf('5 %02d %s', $sequence, $folder);
    }

    private function buildDocumentsManifestLegacy(Project $project, string $downloadDir, ?int $userId, AttachmentPackageRun $run): array
    {
        $evidences = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->where('in_drive', true)
            ->whereNotNull('drive_file_id')
            ->with('requirement')
            ->get();
        $totalEvidence = max(1, $evidences->count());
        $processedEvidence = 0;
        $downloadErrors = [];

        $grouped = $evidences
            ->filter(fn ($e) => $e->requirement)
            ->groupBy(function ($evidence) {
                $req = $evidence->requirement;
                $subgroup = trim((string) ($req->carpeta ?: 'Sin Subgrupo'));
                $groupCode = $this->extractGroupCode((string) ($req->codigo_interno ?? $req->numeracion ?? ''));
                $prefix = str_pad((string) $groupCode, 2, '0', STR_PAD_LEFT);

                return $prefix.'|'.$subgroup;
            });

        $documents = [];
        $usedBaseNames = [];
        $usedEvidenceIds = [];
        foreach ($grouped as $key => $items) {
            [$groupCode, $subgroup] = explode('|', $key, 2);
            $this->appendManifestDocument(
                $documents,
                $usedBaseNames,
                $usedEvidenceIds,
                $items,
                $this->resolveDocumentTitle($groupCode, $subgroup),
                $downloadDir,
                $userId,
                $run,
                $processedEvidence,
                $totalEvidence,
                $downloadErrors
            );
        }

        if (! empty($downloadErrors)) {
            $meta = is_array($run->meta) ? $run->meta : [];
            $meta['download_errors'] = $downloadErrors;
            $meta['download_errors_count'] = count($downloadErrors);
            $meta['heartbeat_at'] = now()->toDateTimeString();
            $run->forceFill(['meta' => $meta])->save();
        }

        return $documents;
    }

    private function uniqueFileName(string $safeName, array &$usedNames): string
    {
        $candidate = $safeName;
        $extension = pathinfo($safeName, PATHINFO_EXTENSION);
        $base = pathinfo($safeName, PATHINFO_FILENAME);
        $counter = 2;
        while (isset($usedNames[mb_strtolower($candidate)])) {
            $candidate = $extension !== ''
                ? $base.' '.$counter.'.'.$extension
                : $base.' '.$counter;
            $counter++;
        }
        $usedNames[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function uniqueBaseName(string $documentTitle, string $groupCode, string $subgroup, array &$usedBaseNames): string
    {
        $preferred = $this->sanitizeFileBase($documentTitle);
        if ($preferred === 'archivo' || $preferred === '') {
            $preferred = $this->sanitizeFileBase($groupCode.' '.$subgroup);
        }
        if ($preferred === 'archivo' || $preferred === '') {
            $preferred = 'documento '.Str::lower($groupCode);
        }

        $candidate = $preferred;
        $i = 2;
        while (in_array(Str::lower($candidate), $usedBaseNames, true)) {
            $candidate = $preferred.' '.$i;
            $i++;
        }

        $usedBaseNames[] = Str::lower($candidate);

        return $candidate;
    }

    private function runPythonGenerator(string $manifestPath): array
    {
        $runtime = app(AttachmentPdfRuntime::class);
        $python = $runtime->pythonBin();
        $script = $runtime->scriptPath();
        if (! file_exists($script)) {
            throw new \RuntimeException('No existe el script Python de adjuntos: '.$script);
        }

        $process = new Process([$python, $script, '--manifest', $manifestPath]);
        $process->setTimeout(1200);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Fallo al ejecutar generador Python: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Respuesta invalida del generador Python.');
        }
        if (! empty($decoded['error'])) {
            throw new \RuntimeException((string) $decoded['error']);
        }

        return $decoded;
    }

    private function buildZip(string $zipPath, string $outputDir, array $pdfFilenames, ?string $missingReport, ?string $generalReport): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP de salida.');
        }

        foreach ($pdfFilenames as $filename) {
            $path = $outputDir.'/'.$filename;
            if (file_exists($path)) {
                $zip->addFile($path, $filename);
            }
        }

        if ($missingReport && file_exists($missingReport)) {
            $zip->addFile($missingReport, basename($missingReport));
        }
        if ($generalReport && file_exists($generalReport)) {
            $zip->addFile($generalReport, basename($generalReport));
        }

        $zip->close();
    }

    private function resolvePackageVersion(string $driveFolderId, ?int $userId, string $mode = 'next'): int
    {
        $max = $this->currentPackageVersion($driveFolderId, $userId);

        if ($mode === 'current') {
            return max(1, $max);
        }

        return $max + 1;
    }

    private function currentPackageVersion(string $driveFolderId, ?int $userId): int
    {
        $files = $this->drive()->listFolderFiles($driveFolderId, $userId);
        $max = 0;
        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if (preg_match('/(?:_|\s)V(\d+)\.(?:zip|pdf)$/i', $name, $m)) {
                $v = (int) $m[1];
                if ($v > $max) {
                    $max = $v;
                }
            }
        }

        return $max;
    }

    private function extractGroupCode(string $code): int
    {
        if (preg_match('/^\s*(\d+)/', $code, $m)) {
            return max(1, min(99, (int) $m[1]));
        }

        return 99;
    }

    private function sanitizeFileBase(string $value): string
    {
        return $this->sanitizeCore(pathinfo($value, PATHINFO_FILENAME));
    }

    private function sanitizeFileName(string $value): string
    {
        $extension = pathinfo($value, PATHINFO_EXTENSION);
        $base = pathinfo($value, PATHINFO_FILENAME);
        $safe = $this->sanitizeCore($base);
        if ($extension === '') {
            return $safe;
        }

        return $safe.'.'.Str::lower($extension);
    }

    private function sanitizeCore(string $value): string
    {
        $value = str_replace(['ñ', 'Ñ'], ['n', 'N'], $value);
        $value = Str::ascii($value);
        $value = str_replace(['.', '_'], ' ', $value);
        $value = preg_replace('/[^A-Za-z0-9 -]+/', '', $value);
        $value = preg_replace('/\s+/', ' ', trim((string) $value));
        $value = trim((string) $value, ' -');

        return $value !== '' ? $value : 'archivo';
    }

    private function cleanupWorkDirectory(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }

        try {
            File::deleteDirectory($workDir);
        } catch (\Throwable) {
            // The run has already been persisted; cleanup must stay best-effort.
        }
    }

    private function drive(): GoogleDriveService
    {
        return app(GoogleDriveService::class);
    }

    private function touchStage(AttachmentPackageRun $run, string $label, int $percent, ?int $detailPercent = null): void
    {
        $this->ensureRunIsNotCancelled($run);

        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['stage_label'] = $label;
        $meta['stage_percent'] = max(0, min(100, $percent));
        $meta['stage_detail_percent'] = $detailPercent !== null ? max(0, min(100, $detailPercent)) : null;
        $meta['heartbeat_at'] = now()->toDateTimeString();

        $run->forceFill(['meta' => $meta])->save();
    }

    private function touchDownloadStage(
        AttachmentPackageRun $run,
        string $fileName,
        string $fileId,
        int $current,
        int $total
    ): void {
        if ($total <= 0) {
            return;
        }

        $this->ensureRunIsNotCancelled($run);

        $detailPercent = (int) round(($current / $total) * 100);
        $overallPercent = 40 + (int) round(($current / $total) * 20);
        $label = 'Descargando evidencia desde Drive ('.$current.'/'.$total.'): '.$fileName;

        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['drive_download_file_id'] = $fileId;
        $meta['drive_download_file_name'] = $fileName;
        $meta['drive_download_started_at'] = now()->toDateTimeString();
        $meta['stage_label'] = mb_strimwidth($label, 0, 180, '...');
        $meta['stage_percent'] = max(0, min(100, $overallPercent));
        $meta['stage_detail_percent'] = max(0, min(100, $detailPercent));
        $meta['heartbeat_at'] = now()->toDateTimeString();

        $run->forceFill(['meta' => $meta])->save();
    }

    private function touchDriveFileStage(AttachmentPackageRun $run, string $context, string $fileName, string $fileId): void
    {
        $this->ensureRunIsNotCancelled($run);

        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['drive_download_file_id'] = $fileId;
        $meta['drive_download_file_name'] = $fileName;
        $meta['drive_download_started_at'] = now()->toDateTimeString();
        $meta['stage_label'] = mb_strimwidth($context.': '.$fileName, 0, 180, '...');
        $meta['drive_folder_download_sequence'] = ((int) ($meta['drive_folder_download_sequence'] ?? 0)) + 1;
        $currentPercent = max(45, (int) ($meta['stage_percent'] ?? 45));
        $meta['stage_percent'] = min(60, max($currentPercent, 44 + (int) $meta['drive_folder_download_sequence']));
        $meta['stage_detail_percent'] = min(100, ((int) ($meta['stage_detail_percent'] ?? 0)) + 1);
        $meta['heartbeat_at'] = now()->toDateTimeString();

        $run->forceFill(['meta' => $meta])->save();
    }

    private function ensureRunIsNotCancelled(AttachmentPackageRun $run): void
    {
        $run->refresh();
        if ($run->status === 'cancelled') {
            throw new \RuntimeException('Proceso cancelado por el usuario.');
        }
    }
}
