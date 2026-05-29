<?php

namespace App\Filament\Resources\PrioridadEntidadResource\Pages;

use App\Filament\Resources\PrioridadEntidadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrioridadEntidads extends ListRecords
{
    protected static string $resource = PrioridadEntidadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

