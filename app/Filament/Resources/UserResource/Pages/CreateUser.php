<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $isAdminRole = $this->record->roles()->where('slug', 'admin')->exists();
        $this->record->is_admin = $isAdminRole;
        $this->record->save();
    }
}
