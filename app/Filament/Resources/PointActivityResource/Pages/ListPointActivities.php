<?php

namespace App\Filament\Resources\PointActivityResource\Pages;

use App\Filament\Resources\PointActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPointActivities extends ListRecords
{
    protected static string $resource = PointActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

