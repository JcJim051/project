<?php

namespace App\Filament\Resources\MeetingAttendanceSessionResource\Pages;

use App\Filament\Resources\MeetingAttendanceSessionResource;
use App\Services\MeetingAttendanceService;
use Filament\Resources\Pages\CreateRecord;

class CreateMeetingAttendanceSession extends CreateRecord
{
    protected static string $resource = MeetingAttendanceSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['public_token'] = app(MeetingAttendanceService::class)->generatePublicToken();
        $data['created_by'] = auth()->id();
        $data['template_version'] = $data['template_version'] ?: app(MeetingAttendanceService::class)->templateVersion();

        return $data;
    }
}
