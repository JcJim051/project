<?php

namespace App\Services;

use App\Models\DriveOAuthSetting;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleDriveService
{
    private const OAUTH_CACHE_KEY = 'drive_oauth_credentials_active';

    public function isConfigured(): bool
    {
        $oauth = $this->oauthCredentials();

        return (bool) ($oauth['client_id'] ?? null)
            && (bool) ($oauth['client_secret'] ?? null)
            && (bool) ($oauth['redirect'] ?? null);
    }

    public function isAuthorized(?int $userId = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        return Storage::disk('local')->exists($this->tokenPath());
    }

    public function getAuthUrl(?int $userId = null, ?string $returnUrl = null): string
    {
        $client = $this->client($userId);
        if ($returnUrl) {
            session(['drive_return_url' => $returnUrl]);
        }

        return $client->createAuthUrl();
    }

    public function handleCallback(string $authCode, ?int $userId = null): void
    {
        $client = $this->client($userId);
        $token = $client->fetchAccessTokenWithAuthCode($authCode);
        if (isset($token['error'])) {
            $description = (string) ($token['error_description'] ?? $token['error']);
            throw new \RuntimeException('OAuth Google rechazó el código: ' . $description);
        }
        $this->storeToken($token, $userId);
    }

    public function syncProjectRequirements(Project $project, Collection $requirements, ?int $userId = null): array
    {
        if (!$project->drive_folder_id) {
            return [
                'total' => 0,
                'matched' => [],
                'unmatched' => [],
                'folders' => [],
            ];
        }

        $targetFolders = [];
        $folderLabelsById = collect();
        $requirementMapByFolderId = collect();

        foreach ($requirements as $req) {
            $resolved = $this->resolveRequirementFolder($project, $req, $userId, false);
            $folderId = $resolved['id'];
            $folderLabel = $resolved['label'];

            if (!array_key_exists($folderLabel, $targetFolders) || ($targetFolders[$folderLabel] === null && $folderId)) {
                $targetFolders[$folderLabel] = $folderId;
            }

            if (!$folderId) {
                continue;
            }

            if (!$folderLabelsById->has($folderId)) {
                $folderLabelsById->put($folderId, collect());
            }
            $folderLabelsById->get($folderId)->push($folderLabel);

            if (!$requirementMapByFolderId->has($folderId)) {
                $requirementMapByFolderId->put($folderId, collect());
            }

            $name = $req->nombre_documento ?: $req->requisito;
            $normalized = $this->normalizeName($name);
            if ($normalized !== '') {
                $requirementMapByFolderId->get($folderId)->put($normalized, $req);
            }
        }

        $uniqueFolderIds = collect($targetFolders)->filter()->unique()->values();

        $filesByFolder = collect();
        try {
            foreach ($uniqueFolderIds as $folderId) {
                $folderLabel = $folderLabelsById->get($folderId, collect())->first() ?: 'Sin carpeta';
                $folderFiles = $this->listFilesInFolder($folderId, $userId)->map(function ($file) use ($folderId, $folderLabel) {
                    $file['rootFolderId'] = $folderId;
                    $file['rootFolder'] = $folderLabel;
                    return $file;
                });
                $filesByFolder = $filesByFolder->merge($folderFiles);
            }
        } catch (\Throwable $e) {
            return [
                'total' => 0,
                'matched' => [],
                'unmatched' => [],
                'folders' => $targetFolders,
                'error' => 'Tiempo de espera agotado al consultar Drive. Intenta nuevamente.',
            ];
        }

        if ($filesByFolder->isEmpty()) {
            return [
                'total' => 0,
                'matched' => [],
                'unmatched' => [],
                'folders' => $targetFolders,
            ];
        }

        RequirementEvidence::where('project_id', $project->id)
            ->whereNotNull('drive_file_id')
            ->update(['in_drive' => false]);

        $matched = [];
        $unmatched = [];
        $currentFileIds = $filesByFolder->pluck('id')->filter()->unique()->values()->all();

        foreach ($filesByFolder as $file) {
            $fileName = $file['name'] ?? '';
            $normalizedFile = $this->normalizeName($fileName);
            if ($normalizedFile === '') {
                continue;
            }

            $folderName = $file['rootFolder'] ?? 'Sin carpeta';
            $folderId = $file['rootFolderId'] ?? null;
            $folderMap = $requirementMapByFolderId->get($folderId, collect());
            $matchedRequirement = $this->matchRequirement($normalizedFile, $folderMap);
            $existing = RequirementEvidence::query()
                ->where('project_id', $project->id)
                ->where('drive_file_id', $file['id'] ?? null)
                ->first();
            if (!$matchedRequirement) {
                if ($existing) {
                    $existing->forceFill([
                        'drive_file_name' => $fileName,
                        'drive_mime_type' => $file['mimeType'] ?? null,
                        'drive_modified_time' => $file['modifiedTime'] ?? null,
                        'in_drive' => true,
                    ])->save();
                }
                $unmatched[] = [
                    'name' => $fileName,
                    'normalized' => $normalizedFile,
                    'folder' => $folderName,
                ];
                continue;
            }

            if ($existing && $existing->source === 'manual_link' && (int) $existing->requirement_id !== (int) $matchedRequirement->id) {
                $existingReq = Requirement::query()->find($existing->requirement_id);
                $existing->forceFill([
                    'drive_file_name' => $fileName,
                    'drive_mime_type' => $file['mimeType'] ?? null,
                    'drive_modified_time' => $file['modifiedTime'] ?? null,
                    'drive_folder_name' => $existingReq?->carpeta ?? $existing->drive_folder_name,
                    'in_drive' => $existingReq ? $this->isValidEvidence($fileName, $file['mimeType'] ?? null, $existingReq) : true,
                ])->save();

                $matched[] = [
                    'file' => $fileName,
                    'normalized' => $normalizedFile,
                    'requirement' => $existingReq?->nombre_documento ?? $existingReq?->requisito ?? ('ID ' . $existing->requirement_id),
                    'folder' => $folderName,
                ];
                continue;
            }

            RequirementEvidence::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'drive_file_id' => $file['id'] ?? null,
                ],
                [
                    'requirement_id' => $matchedRequirement->id,
                    'drive_file_name' => $fileName,
                    'drive_mime_type' => $file['mimeType'] ?? null,
                    'drive_modified_time' => $file['modifiedTime'] ?? null,
                    'drive_folder_name' => $matchedRequirement->carpeta,
                    'source' => 'auto_match',
                    'in_drive' => $this->isValidEvidence($fileName, $file['mimeType'] ?? null, $matchedRequirement),
                ]
            );

            $matched[] = [
                'file' => $fileName,
                'normalized' => $normalizedFile,
                'requirement' => $matchedRequirement->nombre_documento ?? $matchedRequirement->requisito,
                'folder' => $folderName,
            ];
        }

        return [
            'total' => $filesByFolder->count(),
            'matched' => $matched,
            'unmatched' => $unmatched,
            'folders' => $targetFolders,
        ];
    }

    public function uploadEvidence(Project $project, Requirement $requirement, UploadedFile $file, string $targetName, ?int $userId = null): RequirementEvidence
    {
        $drive = $this->drive($userId);
        $parentFolderId = $project->drive_folder_id;

        $resolved = $this->resolveRequirementFolder($project, $requirement, $userId, true);
        $folderId = $resolved['id'] ?? $parentFolderId;

        $driveFile = new DriveFile([
            'name' => $targetName,
            'parents' => [$folderId],
        ]);

        $created = $drive->files->create($driveFile, [
            'data' => file_get_contents($file->getRealPath()),
            'mimeType' => $file->getClientMimeType(),
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,modifiedTime',
        ]);

        return RequirementEvidence::create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => $created->id,
            'drive_file_name' => $created->name,
            'drive_mime_type' => $created->mimeType,
            'drive_modified_time' => $created->modifiedTime,
            'drive_folder_name' => $requirement->carpeta,
            'source' => 'upload',
            'in_drive' => $this->isValidEvidence($created->name, $created->mimeType, $requirement),
        ]);
    }

    public function ensureProjectSubfolder(Project $project, string $folderName, ?int $userId = null): ?string
    {
        if (!$project->drive_folder_id) {
            return null;
        }

        $existing = $this->findDirectChildFolderIdByName($project->drive_folder_id, $folderName, $userId);
        if ($existing) {
            return $existing;
        }

        return $this->createChildFolder($project->drive_folder_id, $folderName, $userId);
    }

    public function listFolderFiles(string $folderId, ?int $userId = null): Collection
    {
        return $this->listFilesInFolder($folderId, $userId);
    }

    public function listRequirementFiles(
        Project $project,
        Requirement $requirement,
        ?int $userId = null,
        ?string $query = null,
        ?string $extension = null
    ): array {
        $resolved = $this->resolveRequirementFolder($project, $requirement, $userId, false);
        $folderId = $resolved['id'] ?? null;
        if (!$folderId) {
            return [
                'folder_label' => $resolved['label'] ?? ($requirement->carpeta ?: 'Sin carpeta'),
                'items' => collect(),
                'total' => 0,
            ];
        }

        $items = $this->listFilesRecursively($folderId, $userId);
        if ($query !== null && trim($query) !== '') {
            $needle = Str::lower(Str::ascii(trim($query)));
            $items = $items->filter(function (array $file) use ($needle) {
                $name = Str::lower(Str::ascii((string) ($file['name'] ?? '')));
                return str_contains($name, $needle);
            })->values();
        }

        if ($extension !== null && trim($extension) !== '') {
            $ext = Str::lower(ltrim(trim($extension), '.'));
            $items = $items->filter(function (array $file) use ($ext) {
                $name = Str::lower((string) ($file['name'] ?? ''));
                return Str::endsWith($name, '.' . $ext);
            })->values();
        }

        return [
            'folder_label' => $resolved['label'] ?? ($requirement->carpeta ?: 'Sin carpeta'),
            'items' => $items->values(),
            'total' => $items->count(),
        ];
    }

    public function getDriveFileMeta(string $fileId, ?int $userId = null): array
    {
        $drive = $this->drive($userId);
        $file = $drive->files->get($fileId, [
            'fields' => 'id,name,mimeType,modifiedTime,parents,size',
        ]);

        return [
            'id' => $file->id ?? $fileId,
            'name' => $file->name ?? $fileId,
            'mimeType' => $file->mimeType ?? null,
            'modifiedTime' => $file->modifiedTime ?? null,
            'parents' => $file->parents ?? [],
            'size' => $file->size ?? null,
        ];
    }

    public function linkRequirementToDriveFile(
        Project $project,
        Requirement $requirement,
        array $fileMeta,
        ?int $userId = null,
        ?string $note = null
    ): RequirementEvidence {
        return RequirementEvidence::updateOrCreate(
            [
                'project_id' => $project->id,
                'drive_file_id' => (string) ($fileMeta['id'] ?? ''),
            ],
            [
                'requirement_id' => $requirement->id,
                'drive_file_name' => (string) ($fileMeta['name'] ?? 'archivo'),
                'drive_mime_type' => $fileMeta['mimeType'] ?? null,
                'drive_modified_time' => $fileMeta['modifiedTime'] ?? null,
                'drive_folder_name' => $requirement->carpeta,
                'source' => 'manual_link',
                'linked_by_user_id' => $userId,
                'linked_at' => now(),
                'link_note' => $note,
                'in_drive' => $this->isValidEvidence((string) ($fileMeta['name'] ?? ''), $fileMeta['mimeType'] ?? null, $requirement),
            ]
        );
    }


    public function renameFile(string $fileId, string $newName, ?int $userId = null): array
    {
        $drive = $this->drive($userId);
        $fileMeta = new DriveFile([
            'name' => $newName,
        ]);

        $updated = $drive->files->update($fileId, $fileMeta, [
            'fields' => 'id,name,mimeType,modifiedTime',
        ]);

        return [
            'id' => $updated->id ?? $fileId,
            'name' => $updated->name ?? $newName,
            'mimeType' => $updated->mimeType ?? null,
            'modifiedTime' => $updated->modifiedTime ?? null,
        ];
    }

    public function deleteFile(string $fileId, ?int $userId = null): void
    {
        $drive = $this->drive($userId);
        $drive->files->delete($fileId);
    }

    public function downloadFile(string $fileId, string $destinationPath, ?int $userId = null): void
    {
        $retries = max(1, (int) config('services.google.download_retries', 4));
        $attempt = 0;
        $lastException = null;

        while ($attempt < $retries) {
            $attempt++;
            try {
                $drive = $this->drive($userId);
                $response = $drive->files->get($fileId, ['alt' => 'media']);
                file_put_contents($destinationPath, $response->getBody()->getContents());
                return;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt >= $retries || !$this->isRetryableDriveError($e)) {
                    break;
                }
                sleep(min(6, $attempt * 2));
            }
        }

        throw $lastException ?: new \RuntimeException('No se pudo descargar archivo desde Drive.');
    }

    public function uploadRawToFolder(string $folderId, string $fileName, string $content, string $mimeType = 'application/octet-stream', ?int $userId = null): array
    {
        $drive = $this->drive($userId);
        $driveFile = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
        ]);

        $created = $drive->files->create($driveFile, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,modifiedTime',
        ]);

        return [
            'id' => $created->id ?? null,
            'name' => $created->name ?? $fileName,
            'mimeType' => $created->mimeType ?? $mimeType,
            'modifiedTime' => $created->modifiedTime ?? null,
        ];
    }

    public function createProjectBaseStructure(
        string $projectName,
        ?int $userId = null,
        ?string $parentFolderId = null,
        ?array $subfolders = null
    ): array {
        $name = trim($projectName);
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre del proyecto para Drive no puede estar vacío.');
        }

        $oauth = $this->oauthCredentials();
        $parentFolderId = $parentFolderId ?: (string) ($oauth['projects_root_folder_id'] ?? config('services.google.projects_root_folder_id', ''));
        $parentFolderId = trim((string) $parentFolderId);
        if ($parentFolderId === '') {
            throw new \RuntimeException('No hay carpeta raíz de proyectos configurada en Drive OAuth.');
        }
        $baseFolders = $subfolders ?: (array) config('services.google.project_base_folders', [
            '01 Estructuracion',
            '02 Cargue',
        ]);
        $structuringFolders = (array) config('services.google.project_structuring_folders', [
            '01 Formulacion',
            '02 Presupuesto',
            '03 Certificaciones',
            '04 Licencias y Permisos',
            '05 Estudios y Diseños',
        ]);

        $drive = $this->drive($userId);
        $payload = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];
        if ($parentFolderId !== '') {
            $payload['parents'] = [$parentFolderId];
        }

        $root = $drive->files->create(new DriveFile($payload), ['fields' => 'id,name,webViewLink']);
        $rootId = (string) ($root->id ?? '');
        if ($rootId === '') {
            throw new \RuntimeException('No se pudo crear la carpeta raíz del proyecto en Drive.');
        }

        $createdSubfolders = [];
        $createdFoldersByName = [];
        foreach (collect($baseFolders)->map(fn ($item) => trim((string) $item))->filter()->unique()->values() as $folder) {
            $createdId = $this->createChildFolder($rootId, $folder, $userId);
            if (!$createdId) {
                throw new \RuntimeException("No se pudo crear la subcarpeta base '{$folder}' en Drive.");
            }
            $createdSubfolders[] = [
                'id' => $createdId,
                'name' => $folder,
            ];
            $createdFoldersByName[$folder] = $createdId;
        }

        $structuringFolderId = $createdFoldersByName['01 Estructuracion'] ?? null;
        if ($structuringFolderId) {
            foreach (collect($structuringFolders)->map(fn ($item) => trim((string) $item))->filter()->unique()->values() as $folder) {
                $createdId = $this->createChildFolder($structuringFolderId, $folder, $userId);
                if (!$createdId) {
                    throw new \RuntimeException("No se pudo crear la subcarpeta de estructuración '{$folder}' en Drive.");
                }
                $createdSubfolders[] = [
                    'id' => $createdId,
                    'name' => '01 Estructuracion/' . $folder,
                ];
            }
        }

        return [
            'id' => $rootId,
            'name' => (string) ($root->name ?? $name),
            'url' => 'https://drive.google.com/drive/folders/' . $rootId,
            'created_subfolders' => $createdSubfolders,
        ];
    }

    public function uploadLocalFileToFolder(
        string $folderId,
        string $fileName,
        string $localPath,
        string $mimeType = 'application/octet-stream',
        ?int $userId = null,
        ?callable $onProgress = null
    ): array
    {
        if (!is_file($localPath)) {
            throw new \RuntimeException('No existe el archivo local para subir: ' . $localPath);
        }

        $size = filesize($localPath);
        if ($size === false || $size < 0) {
            throw new \RuntimeException('No se pudo leer el tamano del archivo local: ' . $localPath);
        }

        $client = $this->client($userId);
        $drive = new Drive($client);
        $client->setDefer(true);

        try {
            $driveFile = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId],
            ]);

            $request = $drive->files->create($driveFile, [
                'uploadType' => 'resumable',
                'fields' => 'id,name,mimeType,modifiedTime',
            ]);

            $chunkSize = 8 * 1024 * 1024;
            $media = new MediaFileUpload($client, $request, $mimeType, '', true, $chunkSize);
            $media->setFileSize($size);

            $status = false;
            $handle = fopen($localPath, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir archivo local para subir: ' . $localPath);
            }

            try {
                while (!$status && !feof($handle)) {
                    $chunk = fread($handle, $chunkSize);
                    if ($chunk === false) {
                        throw new \RuntimeException('Error leyendo bloque del archivo local: ' . $localPath);
                    }
                    $status = $media->nextChunk($chunk);
                    if ($onProgress) {
                        $uploadedBytes = (int) $media->getProgress();
                        $onProgress($uploadedBytes, (int) $size);
                    }
                }
            } finally {
                fclose($handle);
            }

            if (!$status) {
                throw new \RuntimeException('La subida resumible no finalizo correctamente.');
            }

            return [
                'id' => $status->id ?? null,
                'name' => $status->name ?? $fileName,
                'mimeType' => $status->mimeType ?? $mimeType,
                'modifiedTime' => $status->modifiedTime ?? null,
            ];
        } finally {
            $client->setDefer(false);
        }
    }

    private function client(?int $userId = null): Client
    {
        $oauth = $this->oauthCredentials();

        $client = new Client();
        $client->setApplicationName('gestion-proyectos');
        $client->setClientId($oauth['client_id'] ?? null);
        $client->setClientSecret($oauth['client_secret'] ?? null);
        $client->setRedirectUri($oauth['redirect'] ?? null);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([Drive::DRIVE]);
        $timeout = max(30, (int) config('services.google.timeout', 120));
        $connectTimeout = max(5, (int) config('services.google.connect_timeout', 20));
        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'read_timeout' => $timeout,
        ]));

        $token = $this->loadToken($userId);
        if ($token) {
            $client->setAccessToken($token);
            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);
                if ($refreshToken) {
                    try {
                        $client->fetchAccessTokenWithRefreshToken($refreshToken);
                        $this->storeToken($client->getAccessToken(), $userId);
                    } catch (\Throwable $e) {
                        $this->forgetToken($userId);
                        throw new \RuntimeException('Token de Google expirado o revocado. Reconecta Drive.', 0, $e);
                    }
                }
            }
        }

        return $client;
    }

    private function drive(?int $userId = null): Drive
    {
        return new Drive($this->client($userId));
    }

    public function forgetCredentialCache(): void
    {
        Cache::forget(self::OAUTH_CACHE_KEY);
    }

    public function oauthCredentials(): array
    {
        return Cache::remember(self::OAUTH_CACHE_KEY, now()->addMinutes(10), function (): array {
            $fallback = [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect' => config('services.google.redirect'),
                'projects_root_folder_id' => config('services.google.projects_root_folder_id'),
            ];

            try {
                $setting = DriveOAuthSetting::query()->latest('id')->first();
                if (!$setting) {
                    return $fallback;
                }

                return [
                    'client_id' => $setting->client_id ?: $fallback['client_id'],
                    'client_secret' => $setting->client_secret ?: $fallback['client_secret'],
                    'redirect' => $setting->redirect_uri ?: $fallback['redirect'],
                    'projects_root_folder_id' => $setting->projects_root_folder_id ?: $fallback['projects_root_folder_id'],
                ];
            } catch (\Throwable $e) {
                return $fallback;
            }
        });
    }

    private function tokenPath(?int $userId = null): string
    {
        // Connection is global for the whole platform (admin-owned token).
        return 'google-drive-token.json';
    }

    private function loadToken(?int $userId = null): ?array
    {
        $path = $this->tokenPath($userId);
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        $raw = Storage::disk('local')->get($path);
        return json_decode($raw, true);
    }

    private function storeToken(array $token, ?int $userId = null): void
    {
        Storage::disk('local')->put($this->tokenPath($userId), json_encode($token));
    }

    private function forgetToken(?int $userId = null): void
    {
        $path = $this->tokenPath($userId);
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function listFilesInFolder(string $folderId, ?int $userId = null): Collection
    {
        $drive = $this->drive($userId);
        $files = collect();
        $pageToken = null;

        do {
            $response = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'", $folderId),
                'fields' => 'nextPageToken, files(id,name,mimeType,modifiedTime)',
                'pageToken' => $pageToken,
                'pageSize' => 1000,
            ]);

            foreach ($response->files as $file) {
                $files->push([
                    'id' => $file->id,
                    'name' => $file->name,
                    'mimeType' => $file->mimeType,
                    'modifiedTime' => $file->modifiedTime,
                ]);
            }

            $pageToken = $response->nextPageToken;
        } while ($pageToken);

        return $files;
    }

    private function listFilesRecursively(string $folderId, ?int $userId = null): Collection
    {
        $drive = $this->drive($userId);
        $queue = collect([$folderId]);
        $seenFolders = [];
        $files = collect();

        while ($queue->isNotEmpty()) {
            $current = (string) $queue->shift();
            if (isset($seenFolders[$current])) {
                continue;
            }
            $seenFolders[$current] = true;

            $directFiles = $this->listFilesInFolder($current, $userId);
            $files = $files->merge($directFiles);

            $pageToken = null;
            do {
                $response = $drive->files->listFiles([
                    'q' => sprintf("'%s' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $current),
                    'fields' => 'nextPageToken, files(id,name)',
                    'pageToken' => $pageToken,
                    'pageSize' => 200,
                ]);

                foreach ($response->files as $folder) {
                    if (!isset($seenFolders[(string) $folder->id])) {
                        $queue->push((string) $folder->id);
                    }
                }

                $pageToken = $response->nextPageToken;
            } while ($pageToken);
        }

        return $files
            ->unique('id')
            ->values();
    }

    private function findFolderIdByName(string $rootFolderId, string $targetName, ?int $userId = null): ?string
    {
        $drive = $this->drive($userId);
        $queue = collect([$rootFolderId]);
        $targetNormalized = $this->normalizeFolderName($targetName);

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $pageToken = null;

            do {
                $response = $drive->files->listFiles([
                    'q' => sprintf("'%s' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $current),
                    'fields' => 'nextPageToken, files(id,name)',
                    'pageToken' => $pageToken,
                    'pageSize' => 200,
                ]);

                foreach ($response->files as $folder) {
                    $folderName = $folder->name ?? '';
                    if ($this->normalizeFolderName($folderName) === $targetNormalized) {
                        return $folder->id;
                    }
                    $queue->push($folder->id);
                }

                $pageToken = $response->nextPageToken;
            } while ($pageToken);
        }

        return null;
    }

    private function cachedFolderId(Project $project, string $folderName, ?int $userId = null): ?string
    {
        $normalized = $this->normalizeFolderName($folderName);
        if ($normalized === '') {
            return null;
        }

        $cacheKey = "drive_folder:{$project->id}:{$normalized}";
        $cached = Cache::get($cacheKey);
        if ($cached === '__NONE__') {
            return null;
        }
        if (is_string($cached)) {
            return $cached;
        }

        $found = $this->findFolderIdByName($project->drive_folder_id, $folderName, $userId);
        if ($found) {
            Cache::put($cacheKey, $found, now()->addHours(12));
            return $found;
        }

        Cache::put($cacheKey, '__NONE__', now()->addMinutes(10));
        return null;
    }

    private function resolvedFolderId(Project $project, string $folderName, ?int $userId = null): ?string
    {
        $cached = $this->cachedFolderId($project, $folderName, $userId);
        if ($cached) {
            return $cached;
        }

        $normalized = $this->normalizeFolderName($folderName);
        if ($normalized === '') {
            return null;
        }

        $fresh = $this->findFolderIdByName($project->drive_folder_id, $folderName, $userId);
        if ($fresh) {
            $cacheKey = "drive_folder:{$project->id}:{$normalized}";
            Cache::put($cacheKey, $fresh, now()->addHours(12));
            return $fresh;
        }

        return null;
    }

    private function resolveRequirementFolder(Project $project, Requirement $requirement, ?int $userId = null, bool $createStudyFolder = false): array
    {
        $baseFolderName = trim((string) ($requirement->carpeta ?: 'Sin carpeta'));

        if (!$this->isEstudioRequirement($requirement)) {
            $baseFolderId = $this->cachedFolderId($project, $baseFolderName, $userId);
            if (!$baseFolderId) {
                return ['id' => null, 'label' => $baseFolderName];
            }
            return ['id' => $baseFolderId, 'label' => $baseFolderName];
        }

        $estudiosFolderName = $this->findEstudiosBaseFolderName($project, $userId) ?: '05 Estudios y Diseños';
        $estudiosFolderId = $this->resolvedFolderId($project, $estudiosFolderName, $userId);
        if (!$estudiosFolderId) {
            return ['id' => null, 'label' => $estudiosFolderName];
        }

        $studyName = $this->cleanStudyFolderName($this->studyFolderName($requirement));
        if ($studyName === '') {
            return ['id' => $estudiosFolderId, 'label' => $estudiosFolderName];
        }

        $studyFolderId = $this->cachedChildFolderId($project, $estudiosFolderId, $studyName, $userId, $createStudyFolder);
        if (!$studyFolderId) {
            return ['id' => null, 'label' => $estudiosFolderName . ' / ' . $studyName];
        }

        return ['id' => $studyFolderId, 'label' => $estudiosFolderName . ' / ' . $studyName];
    }

    private function cachedChildFolderId(Project $project, string $parentFolderId, string $childFolderName, ?int $userId = null, bool $createIfMissing = false): ?string
    {
        $normalized = $this->normalizeFolderName($childFolderName);
        if ($normalized === '') {
            return null;
        }

        $cacheKey = "drive_folder_child:{$project->id}:{$parentFolderId}:{$normalized}";
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '__NONE__') {
            return $cached;
        }
        if ($cached === '__NONE__' && !$createIfMissing) {
            return null;
        }

        $found = $this->findDirectChildFolderIdByName($parentFolderId, $childFolderName, $userId);
        if ($found) {
            Cache::put($cacheKey, $found, now()->addHours(12));
            return $found;
        }

        if ($createIfMissing) {
            $created = $this->createChildFolder($parentFolderId, $childFolderName, $userId);
            if ($created) {
                Cache::put($cacheKey, $created, now()->addHours(12));
                return $created;
            }
        }

        Cache::put($cacheKey, '__NONE__', now()->addMinutes(10));
        return null;
    }

    private function findDirectChildFolderIdByName(string $parentFolderId, string $targetName, ?int $userId = null): ?string
    {
        $drive = $this->drive($userId);
        $targetNormalized = $this->normalizeFolderName($targetName);
        $targetClean = $this->normalizeStudyFolderKey($targetName);
        $pageToken = null;

        do {
            $response = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $parentFolderId),
                'fields' => 'nextPageToken, files(id,name)',
                'pageToken' => $pageToken,
                'pageSize' => 200,
            ]);

            foreach ($response->files as $folder) {
                $folderName = $folder->name ?? '';
                if ($this->normalizeFolderName($folderName) === $targetNormalized) {
                    return $folder->id;
                }
                if ($targetClean !== '' && $this->normalizeStudyFolderKey($folderName) === $targetClean) {
                    return $folder->id;
                }
            }

            $pageToken = $response->nextPageToken;
        } while ($pageToken);

        return null;
    }

    private function createChildFolder(string $parentFolderId, string $name, ?int $userId = null): ?string
    {
        try {
            $drive = $this->drive($userId);
            $folder = new DriveFile([
                'name' => $name,
                'parents' => [$parentFolderId],
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);

            $created = $drive->files->create($folder, ['fields' => 'id']);
            return $created->id ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isEstudioRequirement(Requirement $requirement): bool
    {
        $folder = $this->normalizeFolderName((string) ($requirement->carpeta ?? ''));
        $code = trim((string) ($requirement->codigo_interno ?? $requirement->numeracion ?? ''));

        if (str_contains($folder, 'estudios y disenos')) {
            return true;
        }

        return (bool) preg_match('/^\s*5(\.|$)/', $code);
    }

    private function studyFolderName(Requirement $requirement): string
    {
        $texto = trim((string) ($requirement->texto ?? ''));
        if ($texto !== '') {
            return $texto;
        }

        $requisito = trim((string) ($requirement->requisito ?? ''));
        if ($requisito !== '' && !in_array(Str::upper($requisito), ['SI', 'NO'], true)) {
            return $requisito;
        }

        return trim((string) ($requirement->carpeta ?? ''));
    }

    private function cleanStudyFolderName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/^\s*[\d.]+[\s\-_]*/u', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function normalizeStudyFolderKey(string $value): string
    {
        $value = $this->cleanStudyFolderName($value);
        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function findEstudiosBaseFolderName(Project $project, ?int $userId = null): ?string
    {
        $candidates = [
            '05 Estudios y Diseños',
            '05 Estudios y Disenos',
            '5 Estudios y Diseños',
            '5 Estudios y Disenos',
            'Estudios y Diseños',
            'Estudios y Disenos',
        ];

        foreach ($candidates as $candidate) {
            $id = $this->cachedFolderId($project, $candidate, $userId);
            if ($id) {
                return $candidate;
            }
        }

        $found = $this->findFolderNameContaining($project->drive_folder_id, 'estudios y disenos', $userId);
        if ($found) {
            return $found;
        }

        return null;
    }

    private function findFolderNameContaining(string $rootFolderId, string $needle, ?int $userId = null): ?string
    {
        $drive = $this->drive($userId);
        $queue = collect([$rootFolderId]);
        $needle = $this->normalizeFolderName($needle);

        while ($queue->isNotEmpty()) {
            $current = $queue->shift();
            $pageToken = null;

            do {
                $response = $drive->files->listFiles([
                    'q' => sprintf("'%s' in parents and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $current),
                    'fields' => 'nextPageToken, files(id,name)',
                    'pageToken' => $pageToken,
                    'pageSize' => 200,
                ]);

                foreach ($response->files as $folder) {
                    $folderName = (string) ($folder->name ?? '');
                    $normalized = $this->normalizeFolderName($folderName);
                    if ($normalized !== '' && str_contains($normalized, $needle)) {
                        return $folderName;
                    }
                    $queue->push($folder->id);
                }

                $pageToken = $response->nextPageToken;
            } while ($pageToken);
        }

        return null;
    }

    private function normalizeFolderName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = Str::ascii($name);
        return Str::lower(trim($name));
    }

    private function normalizeName(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = trim($name);
        $name = preg_replace('/^\s*[\d.]+[\s\-_]*/u', '', $name);
        $name = preg_replace('/\s*\(\d+\)\s*$/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = Str::ascii($name);
        return Str::lower(trim($name));
    }

    private function matchRequirement(string $normalizedFile, Collection $requirementMap): ?Requirement
    {
        if ($requirementMap->has($normalizedFile)) {
            return $requirementMap->get($normalizedFile);
        }

        return null;
    }

    private function isRetryableDriveError(\Throwable $e): bool
    {
        $message = Str::lower($e->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'temporarily unavailable')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'failed to connect')
            || str_contains($message, '429')
            || str_contains($message, '503');
    }

    private function isPdfFile(string $name, ?string $mimeType): bool
    {
        if ($mimeType === 'application/pdf') {
            return true;
        }

        return Str::endsWith(Str::lower($name), '.pdf');
    }

    private function isValidEvidence(string $name, ?string $mimeType, Requirement $requirement): bool
    {
        $requirementName = Str::lower(Str::ascii($requirement->nombre_documento ?? $requirement->requisito ?? ''));
        $fileName = Str::lower($name);

        if (Str::contains($requirementName, 'excel')) {
            if (Str::endsWith($fileName, ['.xls', '.xlsx', '.xlsm'])) {
                return true;
            }
            if (in_array($mimeType, [
                'application/vnd.google-apps.spreadsheet',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'application/csv',
            ], true)) {
                return true;
            }
        }

        if (preg_match('/\bproject\b/', $requirementName)) {
            if (Str::endsWith($fileName, ['.mpp'])) {
                return true;
            }
            if (in_array($mimeType, [
                'application/vnd.ms-project',
                'application/x-msproject',
                'application/octet-stream',
            ], true)) {
                return true;
            }
        }

        if (Str::contains($requirementName, 'powerpoint') || Str::contains($requirementName, 'power point')) {
            if (Str::endsWith($fileName, ['.ppt', '.pptx'])) {
                return true;
            }
            if (in_array($mimeType, [
                'application/vnd.google-apps.presentation',
                'application/vnd.ms-powerpoint',
                'application/powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ], true)) {
                return true;
            }
        }

        return $this->isPdfFile($name, $mimeType);
    }
}
