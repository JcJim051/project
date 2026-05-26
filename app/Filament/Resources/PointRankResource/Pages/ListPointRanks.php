<?php

namespace App\Filament\Resources\PointRankResource\Pages;

use App\Filament\Resources\PointRankResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPointRanks extends ListRecords
{
    protected static string $resource = PointRankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

