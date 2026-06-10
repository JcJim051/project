<?php

namespace App\Console\Commands;

use App\Models\DriveUploadSession;
use App\Models\PointActivity;
use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Console\Command;

class BackfillEvidenceGamificationPoints extends Command
{
    protected $signature = 'gamification:backfill-evidence-points
        {--apply : Registra los puntos; sin esta opción solo muestra el diagnóstico}
        {--project= : Limita el proceso a un proyecto}
        {--user= : Limita el proceso a un usuario}';

    protected $description = 'Recupera puntos de primeras evidencias cargadas mediante sesiones resumibles de Drive.';

    public function handle(GamificationService $gamification): int
    {
        $query = DriveUploadSession::query()
            ->where('status', 'completed')
            ->whereNotNull('user_id')
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->orderBy('id');

        if ($projectId = (int) $this->option('project')) {
            $query->where('project_id', $projectId);
        }

        if ($userId = (int) $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $sessions = $query->get()->unique(
            fn (DriveUploadSession $session): string => $session->project_id.'|'.$session->requirement_id
        );

        $eligible = 0;
        $blockedByRole = 0;
        $awarded = 0;
        $activity = PointActivity::query()
            ->where('code', 'req_first_valid_evidence')
            ->where('enabled', true)
            ->first();

        if (!$activity) {
            $this->error('La actividad req_first_valid_evidence no existe o está desactivada.');
            return self::FAILURE;
        }

        foreach ($sessions as $session) {
            $hasEvidence = RequirementEvidence::query()
                ->where('project_id', $session->project_id)
                ->where('requirement_id', $session->requirement_id)
                ->where('in_drive', true)
                ->exists();

            if (!$hasEvidence) {
                continue;
            }

            $eligible++;
            $user = User::query()->with('roles')->find((int) $session->user_id);
            $roleAllowed = $user && match ((string) $activity->role_scope) {
                'ambos' => $user->hasAnyRole(['formulador', 'estructurador']),
                'formulador' => $user->hasRole('formulador'),
                'estructurador' => $user->hasRole('estructurador'),
                default => false,
            };

            if (!$roleAllowed) {
                $blockedByRole++;
                continue;
            }

            if (!$this->option('apply')) {
                continue;
            }

            $event = $gamification->award(
                'req_first_valid_evidence',
                (int) $session->user_id,
                [
                    'project_id' => (int) $session->project_id,
                    'requirement_id' => (int) $session->requirement_id,
                    'metadata' => [
                        'drive_upload_session_id' => (int) $session->id,
                        'backfilled' => true,
                    ],
                ],
                $session->completed_at
            );

            if ($event) {
                $awarded++;
            }
        }

        if (!$this->option('apply')) {
            $awardable = $eligible - $blockedByRole;
            $this->warn("Diagnóstico: {$eligible} primeras cargas detectadas; {$awardable} puntuables; {$blockedByRole} bloqueadas por rol.");
            $this->line('Ejecuta nuevamente con --apply para registrar los eventos faltantes.');
            return self::SUCCESS;
        }

        $this->info("Recuperación completada. Detectadas: {$eligible}; bloqueadas por rol: {$blockedByRole}; nuevos eventos: {$awarded}.");

        return self::SUCCESS;
    }
}
