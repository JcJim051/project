<?php

namespace App\Filament\Pages;

use App\Mail\ProjectEventMail;
use App\Models\MailSetting;
use App\Services\MailSettingsService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Configuracion';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Correo SMTP';

    protected static ?string $navigationLabel = 'Correo SMTP';

    protected static ?string $slug = 'mail-settings';

    protected static string $view = 'filament.pages.mail-settings';

    public ?array $data = [];
    public bool $isConfigured = false;

    public function mount(MailSettingsService $service): void
    {
        $setting = MailSetting::query()->latest('id')->first();
        $active = $service->activeSettings();

        $this->form->fill([
            'host' => $active['host'] ?? ($setting?->host ?? 'smtp.gmail.com'),
            'port' => (int) ($active['port'] ?? ($setting?->port ?? 587)),
            'username' => $active['username'] ?? ($setting?->username ?? ''),
            'password' => $active['password'] ?? ($setting?->password ?? ''),
            'encryption' => $active['encryption'] ?? ($setting?->encryption ?? 'tls'),
            'from_address' => $active['from_address'] ?? ($setting?->from_address ?? ''),
            'from_name' => $active['from_name'] ?? ($setting?->from_name ?? 'AIM Proyectos'),
            'ehlo_domain' => $active['ehlo_domain'] ?? ($setting?->ehlo_domain ?? ''),
        ]);

        $this->refreshStatus();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('host')
                    ->label('Host SMTP')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El host SMTP es obligatorio.',
                    ]),
                TextInput::make('port')
                    ->label('Puerto SMTP')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->validationMessages([
                        'required' => 'El puerto SMTP es obligatorio.',
                        'numeric' => 'El puerto SMTP debe ser numérico.',
                    ]),
                TextInput::make('username')
                    ->label('Usuario SMTP')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El usuario SMTP es obligatorio.',
                    ]),
                TextInput::make('password')
                    ->label('Password SMTP (App Password)')
                    ->password()
                    ->revealable()
                    ->required()
                    ->validationMessages([
                        'required' => 'El password SMTP es obligatorio.',
                    ]),
                Select::make('encryption')
                    ->label('Cifrado')
                    ->required()
                    ->options([
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                        'null' => 'Sin cifrado',
                    ])
                    ->validationMessages([
                        'required' => 'Debes seleccionar el tipo de cifrado.',
                    ]),
                TextInput::make('from_address')
                    ->label('Correo remitente (From)')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El correo remitente es obligatorio.',
                        'email' => 'El correo remitente no tiene un formato válido.',
                    ]),
                TextInput::make('from_name')
                    ->label('Nombre remitente')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El nombre remitente es obligatorio.',
                    ]),
                TextInput::make('ehlo_domain')->label('EHLO dominio (opcional)')->maxLength(255),
            ]);
    }

    public function save(MailSettingsService $service): void
    {
        $payload = $this->form->getState();

        $setting = MailSetting::query()->latest('id')->first() ?: new MailSetting();
        $setting->fill([
            'host' => $payload['host'],
            'port' => (int) $payload['port'],
            'username' => $payload['username'],
            'password' => $payload['password'],
            'encryption' => $payload['encryption'] === 'null' ? null : $payload['encryption'],
            'from_address' => $payload['from_address'],
            'from_name' => $payload['from_name'],
            'ehlo_domain' => $payload['ehlo_domain'] ?: null,
        ]);
        $setting->updated_by = auth()->id();
        $setting->save();

        $service->clearCache();
        $service->applyRuntimeConfig();
        $this->refreshStatus();

        Notification::make()
            ->title('Credenciales SMTP guardadas')
            ->body('La plataforma usará esta cuenta para notificaciones oficiales.')
            ->success()
            ->send();
    }

    public function sendTestEmail(MailSettingsService $service): void
    {
        $user = auth()->user();
        if (!$user?->email) {
            Notification::make()->title('No se pudo enviar prueba')->body('Tu usuario no tiene email.')->danger()->send();
            return;
        }

        $service->applyRuntimeConfig();
        try {
            Log::info('smtp_test_send_start', [
                'to' => $user->email,
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'from' => config('mail.from.address'),
            ]);
            Mail::mailer('smtp')
                ->to($user->email)
                ->send(new ProjectEventMail(
                    subjectLine: 'Prueba SMTP - AIM Proyectos',
                    title: 'Prueba de notificación oficial',
                    projectName: 'Entorno de pruebas',
                    eventLabel: 'Validación de configuración SMTP',
                    detail: 'Si recibes este correo con diseño HTML, la configuración de envío está correcta y lista para notificaciones oficiales.',
                    actionUrl: url('/panel'),
                    actionLabel: 'Ir al panel'
                ));
            Log::info('smtp_test_send_ok', [
                'to' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('smtp_test_send_failed', [
                'to' => $user->email,
                'error' => $e->getMessage(),
            ]);
            Notification::make()
                ->title('Error enviando correo de prueba')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Correo de prueba enviado')
            ->body("Revisa la bandeja de {$user->email}.")
            ->success()
            ->send();
    }

    private function refreshStatus(): void
    {
        $state = $this->form->getState();
        $this->isConfigured = filled($state['host'] ?? null)
            && filled($state['port'] ?? null)
            && filled($state['username'] ?? null)
            && filled($state['password'] ?? null)
            && filled($state['from_address'] ?? null);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->isAdminUser());
    }
}
