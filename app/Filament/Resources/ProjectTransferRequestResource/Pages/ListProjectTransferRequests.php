<?php

namespace App\Filament\Resources\ProjectTransferRequestResource\Pages;

use App\Filament\Resources\ProjectTransferRequestResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListProjectTransferRequests extends ListRecords
{
    protected static string $resource = ProjectTransferRequestResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')),
            'approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'approved')),
            'rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'rejected')),
            'history' => Tab::make('Historial'),
        ];
    }
}

