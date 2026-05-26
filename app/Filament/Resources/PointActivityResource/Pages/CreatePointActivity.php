<?php

namespace App\Filament\Resources\PointActivityResource\Pages;

use App\Filament\Resources\PointActivityResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePointActivity extends CreateRecord
{
    protected static string $resource = PointActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}

