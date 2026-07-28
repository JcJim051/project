<?php

namespace App\Filament\Resources\ProjectWorkflowStepResource\Pages;

use App\Filament\Resources\ProjectWorkflowStepResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectWorkflowSteps extends ListRecords
{
    protected static string $resource = ProjectWorkflowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
