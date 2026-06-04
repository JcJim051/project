<?php

namespace App\Filament\Pages;

use App\Services\ProcessSettingsService;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProcessSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Configuración del proceso';

    protected static ?string $navigationLabel = 'Configuración del proceso';

    protected static ?string $slug = 'process-settings';

    protected static string $view = 'filament.pages.process-settings';

    public ?array $data = [];

    public function mount(ProcessSettingsService $service): void
    {
        $this->form->fill($service->active());
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Toggle::make('require_planning_aim_approval')
                    ->label('Requerir aprobación de Planeación AIM para generar carteras')
                    ->helperText('Si está apagado, basta la aprobación interna de Dirección. Si está encendido, se requiere doble llave: Dirección + Planeación AIM.')
                    ->inline(false),
            ]);
    }

    public function save(ProcessSettingsService $service): void
    {
        $service->save($this->form->getState(), auth()->id());

        Notification::make()
            ->title('Configuración guardada')
            ->body('Se actualizó la regla de aprobación de Planeación AIM.')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canManageParametrizacion());
    }
}
