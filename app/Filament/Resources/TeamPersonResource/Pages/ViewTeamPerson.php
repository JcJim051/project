<?php

namespace App\Filament\Resources\TeamPersonResource\Pages;

use App\Filament\Resources\TeamPersonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewTeamPerson extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TeamPersonResource::class;
    protected static string $view = 'filament.resources.team-person-resource.pages.view-team-person';

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

    public function getTitle(): string
    {
        return 'Ficha de persona del equipo';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver al equipo')
                ->url(TeamPersonResource::getUrl('index')),
        ];
    }
}
