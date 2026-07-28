<?php

namespace App\Filament\Resources\ProjectWorkflowStageResource\Pages;

use App\Filament\Resources\ProjectWorkflowStageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectWorkflowStages extends ListRecords
{
    protected static string $resource = ProjectWorkflowStageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
