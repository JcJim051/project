<?php

namespace App\Filament\Resources\TeamOnboardingCampaignResource\Pages;

use App\Filament\Resources\TeamOnboardingCampaignResource;
use App\Services\TeamOnboardingService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewTeamOnboardingCampaign extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TeamOnboardingCampaignResource::class;
    protected static string $view = 'filament.resources.team-onboarding-campaign-resource.pages.view-team-onboarding-campaign';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $service = app(TeamOnboardingService::class);

        $this->viewData = [
            'campaign' => $this->record->load('requests'),
            'qrDataUri' => $service->qrSvgDataUri(route('team-onboarding.register', $this->record->public_token)),
            'publicUrl' => route('team-onboarding.campaign', $this->record->public_token),
            'registerUrl' => route('team-onboarding.register', $this->record->public_token),
            'summary' => $service->buildCampaignSummary($this->record),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getTitle(): string
    {
        return 'Detalle de campaña QR';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_public')
                ->label('Abrir enlace público')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(route('team-onboarding.campaign', $this->record->public_token), shouldOpenInNewTab: true),
            Action::make('edit')
                ->label('Editar campaña')
                ->url(TeamOnboardingCampaignResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
