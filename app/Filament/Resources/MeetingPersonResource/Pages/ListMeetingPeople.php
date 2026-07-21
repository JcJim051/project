<?php

namespace App\Filament\Resources\MeetingPersonResource\Pages;

use App\Filament\Resources\MeetingPersonResource;
use Filament\Resources\Pages\ListRecords;

class ListMeetingPeople extends ListRecords
{
    protected static string $resource = MeetingPersonResource::class;
}
