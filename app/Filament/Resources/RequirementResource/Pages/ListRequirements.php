<?php

namespace App\Filament\Resources\RequirementResource\Pages;

use App\Filament\Resources\RequirementResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRequirements extends ListRecords
{
    protected static string $resource = RequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar')
                ->label('Importar XLSX')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(url('/panel/requirements-import')),
            Action::make('exportar')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('requirements.export')),
            Actions\CreateAction::make(),
        ];
    }
}
