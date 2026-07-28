<?php

namespace App\Filament\Resources\ProjectWorkflowStageResource\Pages;

use App\Filament\Resources\ProjectWorkflowStageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectWorkflowStage extends CreateRecord
{
    protected static string $resource = ProjectWorkflowStageResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
