<?php

namespace App\Filament\Resources\SecretariaResource\Pages;

use App\Filament\Resources\SecretariaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSecretarias extends ListRecords
{
    protected static string $resource = SecretariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
