<?php

namespace App\Services;

use App\Models\AttachmentPackageRun;
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
        $this->touchStage($run, 'Preparando proyecto', 5);
        $project = $run->project()->with('requisitos')->firstOrFail();
        $userId = $run->user_id;

        $this->touchStage($run, 'Resolviendo carpeta 02 Cargue', 12);
        $driveFolderId = $this->drive()->ensureProjectSubfolder($project, '02 Cargue', $userId);
        if (!$driveFolderId) {
            throw new \RuntimeException('No se pudo resolver la carpeta 02 Cargue en Drive.');
        }

        $this->touchStage($run, 'Calculando version', 18);
        $version = $this->resolveNextVersion($driveFolderId, $userId);
        $workDir = storage_path('app/tmp/attachment-runs/' . $run->id);
        $downloadDir = $workDir . '/downloads';
        $outputDir = $workDir . '/output';
        File::ensureDirectoryExists($downloadDir);
        File::ensureDirectoryExists($outputDir);

        $this->touchStage($run, 'Descargando evidencias desde Drive', 40);
        $documents = $this->buildDocumentsManifest($project, $downloadDir, $userId, $run);
        if (empty($documents)) {
            throw new \RuntimeException('No fue posible descargar evidencias desde Drive para generar el paquete.');
        }
        $projectBase = $this->sanitizeFileBase('Adjuntos_' . ($project->nombre ?: 'Proyecto'));
        $zipFilename = $projectBase . '_V' . $version . '.zip';
        $zipPath = $outputDir . '/' . $zipFilename;
        $manifestPath = $workDir . '/manifest.json';

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

        $this->touchStage($run, 'Empaquetando ZIP', 76);
        $this->buildZip($zipPath, $outputDir, $generatedPdfs, $missingReport, $generalReport);

        $zipSizeBytes = (int) (filesize($zipPath) ?: 0);
        $run->forceFill([
            'version_number' => $version,
            'zip_filename' => $zipFilename,
            'zip_local_path' => $zipPath,
        ])->save();

        $this->touchStage($run, 'Subiendo ZIP a Drive', 90);
        $lastUploadPercent = -1;
        $uploaded = $this->drive()->uploadLocalFileToFolder(
            $driveFolderId,
            $zipFilename,
            $zipPath,
            'application/zip',
            $userId,
            function (int $uploadedBytes, int $totalBytes) use ($run, &$lastUploadPercent, $zipSizeBytes): void {
                $total = $totalBytes > 0 ? $totalBytes : max(1, $zipSizeBytes);
                $detailPercent = (int) floor(($uploadedBytes / $total) * 100);
                $detailPercent = max(0, min(100, $detailPercent));
                if ($detailPercent === $lastUploadPercent) {
                    return;
                }
                $lastUploadPercent = $detailPercent;

                $overallPercent = 90 + (int) floor($detailPercent / 10);
                $uploadedMb = round($uploadedBytes / 1048576, 1);
                $totalMb = round($total / 1048576, 1);
                $label = 'Subiendo ZIP a Drive (' . $uploadedMb . 'MB/' . $totalMb . 'MB)';
                $this->touchStage($run, $label, $overallPercent, $detailPercent);
            }
        );

        $run->update([
            'version_number' => $version,
            'zip_filename' => $zipFilename,
            'zip_local_path' => $zipPath,
            'drive_folder_id' => $driveFolderId,
            'drive_file_id' => $uploaded['id'] ?? null,
            'generated_pdf_count' => count($generatedPdfs),
            'missing_count' => $missingCount,
            'meta' => [
                'pdf_filenames' => $generatedPdfs,
                'missing_report' => $missingReport,
                'general_report' => $generalReport,
                'python_output' => $pythonResult,
                'stage_label' => 'Finalizado',
                'stage_percent' => 100,
                'heartbeat_at' => now()->toDateTimeString(),
            ],
        ]);

        return $run->fresh();
    }

    private function buildDocumentsManifest(Project $project, string $downloadDir, ?int $userId, AttachmentPackageRun $run): array
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
                $subgroup = trim((string) ($req->carpeta ?: 'Sin_Subgrupo'));
                $groupCode = $this->extractGroupCode((string) ($req->codigo_interno ?? $req->numeracion ?? ''));
                $prefix = str_pad((string) $groupCode, 2, '0', STR_PAD_LEFT);
                return $prefix . '|' . $subgroup;
            });

        $documents = [];
        $usedBaseNames = [];
        foreach ($grouped as $key => $items) {
            [$groupCode, $subgroup] = explode('|', $key, 2);
            $documentTitle = $this->resolveDocumentTitle($groupCode, $subgroup);
            $safeBaseName = $this->uniqueBaseName($documentTitle, $groupCode, $subgroup, $usedBaseNames);
            $docDir = $downloadDir . '/' . $safeBaseName;
            File::ensureDirectoryExists($docDir);

            $files = [];
            foreach ($items as $index => $evidence) {
                $original = (string) ($evidence->drive_file_name ?: ('archivo_' . $index . '.pdf'));
                $safeName = $this->sanitizeFileName($original);
                $localPath = $docDir . '/' . $safeName;
                try {
                    $this->drive()->downloadFile($evidence->drive_file_id, $localPath, $userId);
                    $files[] = [
                        'name' => $safeName,
                        'path' => $localPath,
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
                        'Descargando evidencias desde Drive (' . $processedEvidence . '/' . $totalEvidence . ')',
                        $overallPercent,
                        $detailPercent
                    );
                }
            }

            $documents[] = [
                'title' => $documentTitle,
                'base_name' => $safeBaseName,
                'files' => $files,
            ];
        }

        if (!empty($downloadErrors)) {
            $meta = is_array($run->meta) ? $run->meta : [];
            $meta['download_errors'] = $downloadErrors;
            $meta['download_errors_count'] = count($downloadErrors);
            $meta['heartbeat_at'] = now()->toDateTimeString();
            $run->forceFill(['meta' => $meta])->save();
        }

        return $documents;
    }

    private function resolveDocumentTitle(string $groupCode, string $subgroup): string
    {
        $subgroup = trim((string) $subgroup);
        if ($subgroup !== '' && !preg_match('/^\d+$/', $subgroup)) {
            return $subgroup;
        }

        if ($groupCode === '03') {
            return match ($subgroup) {
                '1' => '3.1 Certificaciones Generales',
                '2' => '3.2 Certificaciones Generales Adicionales',
                '3' => '3.3 Otras Certificaciones',
                '4' => '3.4 Documentos Sectoriales',
                default => '03 Certificaciones',
            };
        }

        if ($groupCode === '04') {
            return '04 Licencias y Permisos';
        }

        if ($groupCode === '05') {
            return $subgroup !== '' ? $subgroup : '05 Estudios y Disenos';
        }

        return $subgroup !== '' ? $subgroup : ('Grupo ' . $groupCode);
    }

    private function uniqueBaseName(string $documentTitle, string $groupCode, string $subgroup, array &$usedBaseNames): string
    {
        $preferred = $this->sanitizeFileBase($documentTitle);
        if ($preferred === 'archivo' || $preferred === '') {
            $preferred = $this->sanitizeFileBase($groupCode . '_' . $subgroup);
        }
        if ($preferred === 'archivo' || $preferred === '') {
            $preferred = 'documento_' . Str::lower($groupCode);
        }

        $candidate = $preferred;
        $i = 2;
        while (in_array(Str::lower($candidate), $usedBaseNames, true)) {
            $candidate = $preferred . '_' . $i;
            $i++;
        }

        $usedBaseNames[] = Str::lower($candidate);
        return $candidate;
    }

    private function runPythonGenerator(string $manifestPath): array
    {
        $python = config('services.attachments_pdf.python_bin', 'python3');
        $script = config('services.attachments_pdf.script_path', base_path('scripts/generate_attachment_pdfs.py'));
        if (!file_exists($script)) {
            throw new \RuntimeException('No existe el script Python de adjuntos: ' . $script);
        }

        $process = new Process([$python, $script, '--manifest', $manifestPath]);
        $process->setTimeout(1200);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Fallo al ejecutar generador Python: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respuesta invalida del generador Python.');
        }
        if (!empty($decoded['error'])) {
            throw new \RuntimeException((string) $decoded['error']);
        }

        return $decoded;
    }

    private function buildZip(string $zipPath, string $outputDir, array $pdfFilenames, ?string $missingReport, ?string $generalReport): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP de salida.');
        }

        foreach ($pdfFilenames as $filename) {
            $path = $outputDir . '/' . $filename;
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

    private function resolveNextVersion(string $driveFolderId, ?int $userId): int
    {
        $files = $this->drive()->listFolderFiles($driveFolderId, $userId);
        $max = 0;
        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if (preg_match('/_V(\d+)\.zip$/i', $name, $m)) {
                $v = (int) $m[1];
                if ($v > $max) {
                    $max = $v;
                }
            }
        }
        return $max + 1;
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
        return $safe . '.' . Str::lower($extension);
    }

    private function sanitizeCore(string $value): string
    {
        $value = str_replace(['ñ', 'Ñ'], ['n', 'N'], $value);
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $value);
        $value = preg_replace('/\s+/', '_', trim($value));
        $value = trim((string) $value, '._-');
        return $value !== '' ? $value : 'archivo';
    }

    private function drive(): GoogleDriveService
    {
        return app(GoogleDriveService::class);
    }

    private function touchStage(AttachmentPackageRun $run, string $label, int $percent, ?int $detailPercent = null): void
    {
        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['stage_label'] = $label;
        $meta['stage_percent'] = max(0, min(100, $percent));
        $meta['stage_detail_percent'] = $detailPercent !== null ? max(0, min(100, $detailPercent)) : null;
        $meta['heartbeat_at'] = now()->toDateTimeString();

        $run->forceFill(['meta' => $meta])->save();
    }
}
