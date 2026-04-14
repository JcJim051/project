<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleDriveService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.google.client_id')
            && (bool) config('services.google.client_secret')
            && (bool) config('services.google.redirect');
    }

    public function isAuthorized(?int $userId = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        return Storage::disk('local')->exists($this->tokenPath($userId));
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

        RequirementEvidence::where('project_id', $project->id)->update(['in_drive' => false]);

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
            if (!$matchedRequirement) {
                $unmatched[] = [
                    'name' => $fileName,
                    'normalized' => $normalizedFile,
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
                    'source' => 'drive',
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

        RequirementEvidence::where('project_id', $project->id)
            ->whereNotNull('drive_file_id')
            ->when(!empty($currentFileIds), function ($query) use ($currentFileIds) {
                $query->whereNotIn('drive_file_id', $currentFileIds);
            })
            ->when(empty($currentFileIds), function ($query) {
                $query->whereRaw('1 = 1');
            })
            ->delete();

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

    private function client(?int $userId = null): Client
    {
        $client = new Client();
        $client->setApplicationName('gestion-proyectos');
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([Drive::DRIVE]);
        $client->setHttpClient(new \GuzzleHttp\Client([
            'timeout' => 10,
            'connect_timeout' => 5,
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

    private function tokenPath(?int $userId = null): string
    {
        if ($userId) {
            return "google-drive-token-{$userId}.json";
        }

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
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
