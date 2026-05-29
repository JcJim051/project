<?php

namespace App\Filament\Resources\MunicipioTipoResource\Pages;

use App\Filament\Resources\MunicipioTipoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMunicipioTipos extends ListRecords
{
    protected static string $resource = MunicipioTipoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

