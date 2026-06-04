<?php

namespace App\Services;

use App\Mail\ProjectEventMail;
use App\Models\Project;
use App\Models\ProjectTransferRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OfficialEmailNotificationService
{
    public function sendAssignment(Project $project, User $toUser, string $roleLabel, bool $isNew): void
    {
        $subject = $isNew
            ? "Nuevo proyecto asignado: {$this->projectName($project)}"
            : "Asignación actualizada: {$this->projectName($project)}";

        $detail = $isNew
            ? "Se te asignó este proyecto con el rol {$roleLabel}."
            : "Tu asignación en este proyecto fue actualizada para el rol {$roleLabel}.";

        $this->sendToUser(
            $toUser,
            new ProjectEventMail(
                $subject,
                'Notificación de asignación',
                $this->projectName($project),
                $isNew ? 'Nuevo proyecto asignado' : 'Asignación actualizada',
                $detail,
                route('filament.admin.resources.projects.manage', ['record' => $project]),
                'Abrir proyecto'
            )
        );
    }

    public function sendMgaSubmitted(Project $project, Collection $users, User $sender, ?string $note, ProjectTransferRequest $request): void
    {
        $detail = trim((string) $note);
        if ($detail === '') {
            $detail = "El proyecto fue enviado a evaluación por {$sender->name}.";
        }

        $mail = new ProjectEventMail(
            "Nueva solicitud MGA: {$this->projectName($project)}",
            'Nueva asignación para evaluación MGA',
            $this->projectName($project),
            'Pendiente de evaluación',
            $detail,
            route('filament.admin.resources.project-transfer-requests.review', ['record' => $request]),
            'Evaluar solicitud'
        );

        foreach ($users as $user) {
            $this->sendToUser($user, $mail);
        }
    }

    public function sendMgaDecision(Project $project, Collection $users, string $decision, ?string $decisionNote): void
    {
        $approved = $decision === 'approve' || $decision === 'approved';
        $event = $approved ? 'Solicitud aprobada' : 'Solicitud rechazada';
        $subject = ($approved ? 'Aprobación MGA: ' : 'Rechazo MGA: ') . $this->projectName($project);

        $mail = new ProjectEventMail(
            $subject,
            'Resultado de revisión interna MGA',
            $this->projectName($project),
            $event,
            trim((string) $decisionNote) ?: null,
            route('filament.admin.resources.projects.manage', ['record' => $project]),
            'Abrir gestionar'
        );

        foreach ($users as $user) {
            $this->sendToUser($user, $mail);
        }
    }


    public function sendProjectEvent(Project $project, Collection $users, string $subject, string $title, string $eventLabel, ?string $detail, ?string $actionUrl, ?string $actionLabel): void
    {
        $mail = new ProjectEventMail(
            $subject,
            $title,
            $this->projectName($project),
            $eventLabel,
            $detail,
            $actionUrl,
            $actionLabel
        );

        foreach ($users as $user) {
            $this->sendToUser($user, $mail);
        }
    }
    private function sendToUser(?User $user, ProjectEventMail $mail): void
    {
        if (!$user || empty($user->email)) {
            return;
        }

        try {
            Mail::mailer('smtp')->to($user->email)->send(clone $mail);
        } catch (\Throwable $e) {
            Log::error('official_email_send_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $mail->subjectLine ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function projectName(Project $project): string
    {
        return (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
    }
}
