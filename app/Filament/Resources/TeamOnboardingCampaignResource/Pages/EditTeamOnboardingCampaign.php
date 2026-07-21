<?php

namespace App\Filament\Resources\TeamOnboardingCampaignResource\Pages;

use App\Filament\Resources\TeamOnboardingCampaignResource;
use Filament\Resources\Pages\EditRecord;

class EditTeamOnboardingCampaign extends EditRecord
{
    protected static string $resource = TeamOnboardingCampaignResource::class;

    public function getTitle(): string
    {
        return 'Editar campaña QR';
    }
}
