<?php

namespace App\Filament\Resources\OperationalModuleResource\Pages;

use App\Filament\Resources\OperationalModuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperationalModule extends EditRecord
{
    protected static string $resource = OperationalModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
