<?php

namespace App\Filament\Resources\ExecutionYearResource\Pages;

use App\Filament\Resources\ExecutionYearResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExecutionYears extends ListRecords
{
    protected static string $resource = ExecutionYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

