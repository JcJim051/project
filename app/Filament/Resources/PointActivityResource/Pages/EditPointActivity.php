<?php

namespace App\Filament\Resources\PointActivityResource\Pages;

use App\Filament\Resources\PointActivityResource;
use Filament\Resources\Pages\EditRecord;

class EditPointActivity extends EditRecord
{
    protected static string $resource = PointActivityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}

