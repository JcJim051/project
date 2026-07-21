<?php

namespace App\Filament\Resources\TeamOnboardingRequestResource\Pages;

use App\Filament\Resources\TeamOnboardingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamOnboardingRequests extends ListRecords
{
    protected static string $resource = TeamOnboardingRequestResource::class;

    public function getTitle(): string
    {
        return 'Solicitudes de caracterización';
    }
}
