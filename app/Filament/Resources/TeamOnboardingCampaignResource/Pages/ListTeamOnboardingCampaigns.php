<?php

namespace App\Filament\Resources\TeamOnboardingCampaignResource\Pages;

use App\Filament\Resources\TeamOnboardingCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamOnboardingCampaigns extends ListRecords
{
    protected static string $resource = TeamOnboardingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Crear campaña QR'),
        ];
    }

    public function getTitle(): string
    {
        return 'Campañas QR';
    }
}
