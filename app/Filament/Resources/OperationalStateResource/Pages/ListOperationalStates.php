<?php

namespace App\Filament\Resources\OperationalStateResource\Pages;

use App\Filament\Resources\OperationalStateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperationalStates extends ListRecords
{
    protected static string $resource = OperationalStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo estado'),
        ];
    }
}
