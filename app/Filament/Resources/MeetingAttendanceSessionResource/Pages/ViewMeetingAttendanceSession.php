<?php

namespace App\Filament\Resources\MeetingAttendanceSessionResource\Pages;

use App\Filament\Resources\MeetingAttendanceSessionResource;
use App\Services\MeetingAttendanceService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewMeetingAttendanceSession extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MeetingAttendanceSessionResource::class;
    protected static string $view = 'filament.resources.meeting-attendance-session-resource.pages.view-meeting-attendance-session';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $service = app(MeetingAttendanceService::class);

        $this->viewData = [
            'session' => $this->record->load('entries.person'),
            'qrDataUri' => $service->qrSvgDataUri(route('attendance.register', $this->record->public_token)),
            'publicUrl' => route('attendance.session', $this->record->public_token),
            'registerUrl' => route('attendance.register', $this->record->public_token),
            'summary' => $service->buildSessionSummary($this->record),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_public')
                ->label('Abrir enlace público')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(route('attendance.session', $this->record->public_token), shouldOpenInNewTab: true),
            Action::make('download_xlsx')
                ->label('Descargar XLSX')
                ->url(route('attendance.sessions.download.xlsx', $this->record)),
            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->url(route('attendance.sessions.download.pdf', $this->record)),
            Action::make('edit')
                ->label('Editar sesión')
                ->url(MeetingAttendanceSessionResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
