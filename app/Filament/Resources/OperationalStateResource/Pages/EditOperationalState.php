<?php

namespace App\Filament\Resources\OperationalStateResource\Pages;

use App\Filament\Resources\OperationalStateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperationalState extends EditRecord
{
    protected static string $resource = OperationalStateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
