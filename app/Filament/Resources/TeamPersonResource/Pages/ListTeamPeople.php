<?php

namespace App\Filament\Resources\TeamPersonResource\Pages;

use App\Filament\Resources\TeamPersonResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamPeople extends ListRecords
{
    protected static string $resource = TeamPersonResource::class;

    public function getTitle(): string
    {
        return 'Personas del equipo';
    }
}
