<?php

namespace App\Filament\Resources\ProjectStageResource\Pages;

use App\Filament\Resources\ProjectStageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectStages extends ListRecords
{
    protected static string $resource = ProjectStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

