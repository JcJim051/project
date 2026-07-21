<?php

namespace App\Filament\Resources\MeetingPersonResource\Pages;

use App\Filament\Resources\MeetingPersonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewMeetingPerson extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MeetingPersonResource::class;
    protected static string $view = 'filament.resources.meeting-person-resource.pages.view-meeting-person';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->viewData = [
            'person' => $this->record->load(['attendanceEntries.session']),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver al directorio')
                ->url(MeetingPersonResource::getUrl('index')),
        ];
    }
}
