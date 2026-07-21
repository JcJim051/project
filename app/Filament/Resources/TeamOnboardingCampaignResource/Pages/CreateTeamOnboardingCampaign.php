<?php

namespace App\Filament\Resources\TeamOnboardingCampaignResource\Pages;

use App\Filament\Resources\TeamOnboardingCampaignResource;
use App\Services\TeamOnboardingService;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamOnboardingCampaign extends CreateRecord
{
    protected static string $resource = TeamOnboardingCampaignResource::class;

    public function getTitle(): string
    {
        return 'Crear campaña QR';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['public_token'] = app(TeamOnboardingService::class)->generatePublicToken();
        $data['created_by'] = auth()->id();

        return $data;
    }
}
