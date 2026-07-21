<?php

namespace App\Filament\Resources\TeamOnboardingRequestResource\Pages;

use App\Filament\Resources\TeamOnboardingRequestResource;
use App\Services\TeamOnboardingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewTeamOnboardingRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TeamOnboardingRequestResource::class;
    protected static string $view = 'filament.resources.team-onboarding-request-resource.pages.view-team-onboarding-request';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->viewData = [
            'requestItem' => $this->record->load(['campaign', 'approvedBy', 'rejectedBy', 'createdUser.roles', 'createdSpecialist']),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getTitle(): string
    {
        return 'Ficha de solicitud';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Aprobar solicitud')
                ->modalDescription('Se creará el registro correspondiente en la plataforma según el rol solicitado.')
                ->modalSubmitActionLabel('Sí, aprobar')
                ->action(function (): void {
                    try {
                        app(TeamOnboardingService::class)->approveRequest($this->record, auth()->user());
                        $this->record = $this->record->fresh();
                        $this->viewData['requestItem'] = $this->record->load(['campaign', 'approvedBy', 'rejectedBy', 'createdUser.roles', 'createdSpecialist']);
                        Notification::make()->title('Solicitud aprobada')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('No se pudo aprobar')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reject')
                ->label('Rechazar')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->modalHeading('Rechazar solicitud')
                ->modalDescription('Esta solicitud quedará marcada como rechazada y no creará ningún registro.')
                ->modalSubmitActionLabel('Sí, rechazar')
                ->form([
                    Textarea::make('review_notes')->label('Observación')->rows(3),
                ])
                ->action(function (array $data): void {
                    app(TeamOnboardingService::class)->rejectRequest($this->record, auth()->user(), $data['review_notes'] ?? null);
                    $this->record = $this->record->fresh();
                    $this->viewData['requestItem'] = $this->record->load(['campaign', 'approvedBy', 'rejectedBy', 'createdUser.roles', 'createdSpecialist']);
                    Notification::make()->title('Solicitud rechazada')->success()->send();
                }),
            Action::make('volver')
                ->label('Volver al listado')
                ->url(TeamOnboardingRequestResource::getUrl('index')),
        ];
    }
}
