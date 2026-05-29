<?php

namespace App\Filament\Resources\ProfesionalAmbientalResource\Pages;

use App\Filament\Resources\ProfesionalAmbientalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProfesionalAmbientals extends ListRecords
{
    protected static string $resource = ProfesionalAmbientalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

