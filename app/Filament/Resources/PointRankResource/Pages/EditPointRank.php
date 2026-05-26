<?php

namespace App\Filament\Resources\PointRankResource\Pages;

use App\Filament\Resources\PointRankResource;
use Filament\Resources\Pages\EditRecord;

class EditPointRank extends EditRecord
{
    protected static string $resource = PointRankResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }
}

