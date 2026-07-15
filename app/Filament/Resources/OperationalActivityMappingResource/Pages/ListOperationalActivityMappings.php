<?php

namespace App\Filament\Resources\OperationalActivityMappingResource\Pages;

use App\Filament\Resources\OperationalActivityMappingResource;
use App\Services\OperationalActivityMappingService;
use Filament\Resources\Pages\ListRecords;

class ListOperationalActivityMappings extends ListRecords
{
    protected static string $resource = OperationalActivityMappingResource::class;

    public function mount(): void
    {
        app(OperationalActivityMappingService::class)->ensureDefaults();
        parent::mount();
    }
}
