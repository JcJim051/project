<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserBulkImportService
{
    public function importFromSpreadsheet(string $path, int $actorId): UserBulkImportResult
    {
        $result = new UserBulkImportResult();
        $onboarding = app(UserOnboardingService::class);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('users_import') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, true, true);
        if (count($rows) < 2) {
            $result->addMessage(1, 'error', 'La hoja users_import no contiene filas de datos.');
            return $result;
        }

        $headers = array_map(fn ($v) => trim((string) $v), $rows[1] ?? []);
        $headerMap = [];
        foreach ($headers as $col => $header) {
            if ($header !== '') {
                $headerMap[$header] = $col;
            }
        }

        $requiredHeaders = ['name', 'email', 'password'];
        foreach ($requiredHeaders as $h) {
            if (!isset($headerMap[$h])) {
                $result->addMessage(1, 'error', "Falta la columna requerida: {$h}");
            }
        }
        if (!empty($result->messages)) {
            return $result;
        }

        $defaultRoleId = (int) (Role::query()->where('slug', 'consulta')->value('id') ?? 0);

        for ($i = 2; $i <= count($rows); $i++) {
            $line = $i;
            $excelRow = $rows[$i] ?? [];
            if ($this->isEmptyRow($excelRow)) {
                continue;
            }
            $row = $this->rowToAssoc($excelRow, $headerMap);

            // JSON-like internal normalized payload (same approach used in project import)
            $payload = [
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                'password' => (string) ($row['password'] ?? ''),
                'role_slug' => strtolower(trim((string) ($row['role_slug'] ?? 'consulta'))),
            ];

            if ($payload['name'] === '' || $payload['email'] === '' || trim($payload['password']) === '') {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Faltan campos obligatorios (name, email, password).');
                continue;
            }
            if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Email inválido.');
                continue;
            }
            if (User::query()->where('email', $payload['email'])->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', "El email {$payload['email']} ya existe.");
                continue;
            }

            $roleId = $defaultRoleId;
            if ($payload['role_slug'] !== '') {
                $roleId = (int) (Role::query()->where('slug', $payload['role_slug'])->value('id') ?? 0);
                if ($roleId <= 0) {
                    $result->skipped++;
                    $result->addMessage($line, 'error', "role_slug inválido: {$payload['role_slug']}.");
                    continue;
                }
            }

            try {
                $user = User::query()->create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Hash::make($payload['password']),
                    'is_admin' => false,
                    'must_change_password' => true,
                ]);
                if ($roleId > 0) {
                    $user->roles()->sync([$roleId]);
                }
                $result->created++;

                $sent = $onboarding->sendWelcomeEmail($user);
                if ($sent) {
                    $result->emailsSent++;
                } else {
                    $result->emailsFailed++;
                    $result->warnings++;
                    $result->addMessage($line, 'warning', 'Usuario creado, pero no se pudo enviar correo de bienvenida.');
                }
            } catch (\Throwable $e) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Error al crear usuario: ' . $e->getMessage());
            }
        }

        Log::info('User bulk import executed', [
            'actor_id' => $actorId,
            'created' => $result->created,
            'skipped' => $result->skipped,
            'warnings' => $result->warnings,
            'messages' => $result->messages,
        ]);

        return $result;
    }

    private function rowToAssoc(array $row, array $headerMap): array
    {
        $out = [];
        foreach ($headerMap as $header => $col) {
            $out[$header] = $row[$col] ?? null;
        }
        return $out;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }
}
