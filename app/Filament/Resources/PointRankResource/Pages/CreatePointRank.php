<?php

namespace App\Filament\Resources\PointRankResource\Pages;

use App\Filament\Resources\PointRankResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePointRank extends CreateRecord
{
    protected static string $resource = PointRankResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}

