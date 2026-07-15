<?php

namespace App\Filament\Resources\OperationalModuleResource\Pages;

use App\Filament\Resources\OperationalModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperationalModules extends ListRecords
{
    protected static string $resource = OperationalModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nuevo módulo'),
        ];
    }
}
