<?php

namespace App\Filament\Resources\MeetingAttendanceSessionResource\Pages;

use App\Filament\Resources\MeetingAttendanceSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeetingAttendanceSessions extends ListRecords
{
    protected static string $resource = MeetingAttendanceSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear token'),
        ];
    }
}
