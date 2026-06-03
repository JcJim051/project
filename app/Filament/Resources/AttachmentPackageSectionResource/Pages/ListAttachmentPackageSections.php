<?php

namespace App\Filament\Resources\AttachmentPackageSectionResource\Pages;

use App\Filament\Resources\AttachmentPackageSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttachmentPackageSections extends ListRecords
{
    protected static string $resource = AttachmentPackageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
