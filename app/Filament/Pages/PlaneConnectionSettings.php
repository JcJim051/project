<?php

namespace App\Filament\Pages;

use App\Models\PlaneConnection;
use App\Services\PlaneProvisioningService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class PlaneConnectionSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Conexión Plane';
    protected static ?string $navigationLabel = 'Conexión Plane';
    protected static ?string $slug = 'plane-connection-settings';
    protected static string $view = 'filament.pages.plane-connection-settings';

    public ?array $data = [];
    public bool $isConfigured = false;
    public ?string $connectionStatus = null;
    public ?string $connectionMessage = null;

    public function mount(PlaneProvisioningService $service): void
    {
        $record = PlaneConnection::query()->latest('id')->first();
        $this->form->fill([
            'nombre' => $record?->nombre ?? 'Plane principal',
            'entorno' => $record?->entorno ?? 'pruebas',
            'url_base' => $record?->url_base ?? '',
            'workspace_id' => $record?->workspace_id ?? '',
            'auth_type' => $record?->auth_type ?? 'api_key',
            'oauth_token_url' => $record?->oauth_token_url ?? '',
            'healthcheck_path' => $record?->healthcheck_path ?? '/api/v1/workspaces/{workspace_slug}/projects/',
            'projects_path' => $record?->projects_path ?? '/api/v1/workspaces/{workspace_slug}/projects/',
            'modules_path_template' => $record?->modules_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/modules/',
            'states_path_template' => $record?->states_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/states/',
            'labels_path_template' => $record?->labels_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/labels/',
            'cycles_path_template' => $record?->cycles_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/cycles/',
            'cycle_issues_path_template' => $record?->cycle_issues_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/cycles/{cycle_id}/cycle-issues/',
            'issues_path_template' => $record?->issues_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/',
            'issue_detail_path_template' => $record?->issue_detail_path_template ?? '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/{issue_id}/',
            'project_url_template' => $this->normalizedProjectUrlTemplate($record?->project_url_template),
            'api_key_header' => $record?->api_key_header ?? 'X-API-Key',
            'api_secret_header' => $record?->api_secret_header ?? 'X-API-Secret',
            'api_key' => $record?->api_key ?? '',
            'api_secret' => $record?->api_secret ?? '',
            'access_token' => $record?->access_token ?? '',
            'client_id' => $record?->client_id ?? '',
            'client_secret' => $record?->client_secret ?? '',
            'activo' => $record?->activo ?? false,
            'timeout_segundos' => $record?->timeout_segundos ?? 15,
        ]);
        $this->refreshStatus($service, $record);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('nombre')->required()->maxLength(255),
                Select::make('entorno')->options(['pruebas' => 'Pruebas', 'produccion' => 'Producción'])->required()->native(false),
                TextInput::make('url_base')->label('URL base')->required()->url()->maxLength(1000),
                TextInput::make('workspace_id')->label('Workspace slug')->required()->maxLength(255)->helperText('Ejemplo: gestion-personal. Se toma de la URL de Plane.'),
                Select::make('auth_type')
                    ->label('Tipo de autenticación')
                    ->options([
                        'bearer_token' => 'Bearer token',
                        'api_key' => 'API key',
                        'oauth_client_credentials' => 'OAuth client credentials',
                    ])->required()->native(false)->live(),
                TextInput::make('oauth_token_url')->label('OAuth token URL')->maxLength(1000)->visible(fn ($get) => $get('auth_type') === 'oauth_client_credentials'),
                TextInput::make('healthcheck_path')->label('Ruta de prueba')->required()->maxLength(255)->helperText('Usa una ruta GET válida de la API, por ejemplo el listado de proyectos del workspace.'),
                TextInput::make('projects_path')->label('Ruta crear proyecto')->required()->maxLength(255),
                TextInput::make('modules_path_template')->label('Ruta crear módulos')->required()->maxLength(255),
                TextInput::make('states_path_template')->label('Ruta crear estados')->required()->maxLength(255),
                TextInput::make('labels_path_template')->label('Ruta crear etiquetas')->required()->maxLength(255),
                TextInput::make('cycles_path_template')->label('Ruta crear ciclos')->required()->maxLength(255),
                TextInput::make('cycle_issues_path_template')->label('Ruta asignar tareas a ciclos')->required()->maxLength(255),
                TextInput::make('issues_path_template')->label('Ruta crear/listar tareas')->required()->maxLength(255),
                TextInput::make('issue_detail_path_template')->label('Ruta detalle tarea')->required()->maxLength(255)->helperText('Puede usar {workspace_slug}, {project_id} y {issue_id}.'),
                TextInput::make('project_url_template')->label('Ruta abrir proyecto en Plane')->required()->maxLength(255)->helperText('Puede usar {workspace_slug} y {project_id}.'),
                TextInput::make('api_key_header')->maxLength(255)->visible(fn ($get) => $get('auth_type') === 'api_key'),
                TextInput::make('api_secret_header')->maxLength(255)->visible(fn ($get) => $get('auth_type') === 'api_key'),
                Textarea::make('api_key')->rows(2)->visible(fn ($get) => $get('auth_type') === 'api_key'),
                Textarea::make('api_secret')->rows(2)->visible(fn ($get) => $get('auth_type') === 'api_key'),
                Textarea::make('access_token')->rows(2)->visible(fn ($get) => $get('auth_type') === 'bearer_token'),
                Textarea::make('client_id')->rows(2)->visible(fn ($get) => $get('auth_type') === 'oauth_client_credentials'),
                Textarea::make('client_secret')->rows(2)->visible(fn ($get) => $get('auth_type') === 'oauth_client_credentials'),
                TextInput::make('timeout_segundos')->numeric()->required()->minValue(1)->maxValue(120),
                Toggle::make('activo')->default(false),
            ]);
    }

    public function save(PlaneProvisioningService $service): void
    {
        $payload = $this->normalizedPayload($this->form->getState());
        $record = PlaneConnection::query()->latest('id')->first() ?: new PlaneConnection();
        if (($payload['activo'] ?? false) === true) {
            PlaneConnection::query()->where('id', '<>', $record->id)->update(['activo' => false]);
        }
        $record->fill($payload);
        $record->api_key_header = $payload['api_key_header'] ?? 'X-API-Key';
        $record->api_secret_header = $payload['api_secret_header'] ?? 'X-API-Secret';
        $record->updated_by = auth()->id();
        $record->save();

        Notification::make()
            ->title('Conexión Plane guardada')
            ->body('La configuración de Plane se actualizó correctamente.')
            ->success()
            ->send();

        $this->refreshStatus($service, $record->fresh());
    }

    public function saveAndTest(PlaneProvisioningService $service): void
    {
        $this->save($service);
        $this->testConnection($service);
    }

    public function testConnection(PlaneProvisioningService $service): void
    {
        $payload = $this->normalizedPayload($this->form->getState());
        $record = PlaneConnection::query()->latest('id')->first() ?: new PlaneConnection();
        $record->fill($payload);
        $record->api_key_header = $payload['api_key_header'] ?? 'X-API-Key';
        $record->api_secret_header = $payload['api_secret_header'] ?? 'X-API-Secret';
        $result = $service->testConnection($record);

        if ($record->exists) {
            $record->forceFill([
                'ultima_prueba_at' => now(),
                'ultimo_estado_prueba' => $result['status'] ?? null,
                'ultimo_mensaje_prueba' => $result['message'] ?? null,
            ])->save();
        }

        Notification::make()
            ->title($result['success'] ? 'Conexión correcta' : 'No se pudo conectar')
            ->body($result['message'] ?? 'Sin detalle adicional.')
            ->{$result['success'] ? 'success' : 'danger'}()
            ->send();

        $this->refreshStatus($service, $record->exists ? $record->fresh() : $record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Probar conexión')
                ->action('testConnection'),
            Action::make('saveAndTest')
                ->label('Guardar y probar')
                ->color('success')
                ->action('saveAndTest'),
        ];
    }


    private function normalizedPayload(array $payload): array
    {
        $authType = (string) ($payload['auth_type'] ?? 'api_key');

        $payload['api_key_header'] = trim((string) ($payload['api_key_header'] ?? '')) ?: 'X-API-Key';
        $payload['api_secret_header'] = trim((string) ($payload['api_secret_header'] ?? '')) ?: 'X-API-Secret';
        $payload['api_key'] = trim((string) ($payload['api_key'] ?? '')) ?: null;
        $payload['api_secret'] = trim((string) ($payload['api_secret'] ?? '')) ?: null;
        $payload['access_token'] = trim((string) ($payload['access_token'] ?? '')) ?: null;
        $payload['client_id'] = trim((string) ($payload['client_id'] ?? '')) ?: null;
        $payload['client_secret'] = trim((string) ($payload['client_secret'] ?? '')) ?: null;
        $payload['oauth_token_url'] = trim((string) ($payload['oauth_token_url'] ?? '')) ?: null;
        $payload['project_url_template'] = $this->normalizedProjectUrlTemplate($payload['project_url_template'] ?? null);

        if ($authType === 'api_key') {
            $payload['access_token'] = null;
            $payload['client_id'] = null;
            $payload['client_secret'] = null;
            $payload['oauth_token_url'] = null;
        }

        if ($authType === 'bearer_token') {
            $payload['api_key'] = null;
            $payload['api_secret'] = null;
            $payload['client_id'] = null;
            $payload['client_secret'] = null;
            $payload['oauth_token_url'] = null;
        }

        if ($authType === 'oauth_client_credentials') {
            $payload['api_key'] = null;
            $payload['api_secret'] = null;
            $payload['access_token'] = null;
        }

        return $payload;
    }

    private function normalizedProjectUrlTemplate(?string $template): string
    {
        $template = trim((string) ($template ?? ''));

        if ($template === '') {
            return '/{workspace_slug}/projects/{project_id}/issues/';
        }

        if (Str::startsWith($template, ['http://', 'https://'])) {
            return $template;
        }

        $template = '/' . ltrim($template, '/');

        if (! Str::contains($template, '{workspace_slug}')) {
            if (Str::startsWith($template, '/projects/')) {
                $template = '/{workspace_slug}' . $template;
            } else {
                $template = '/{workspace_slug}/' . ltrim($template, '/');
            }
        }

        if (preg_match('#/\{project_id\}/?$#', $template) === 1 && ! Str::contains($template, '/issues')) {
            $template = rtrim($template, '/') . '/issues/';
        }

        $template = preg_replace('#\s+/#', '/', $template) ?? $template;
        $template = preg_replace('#/\s+#', '/', $template) ?? $template;

        return $template;
    }

    private function refreshStatus(PlaneProvisioningService $service, ?PlaneConnection $record): void
    {
        $this->isConfigured = filled($record?->url_base);
        $this->connectionStatus = $record?->ultimo_estado_prueba;
        $this->connectionMessage = $record?->ultimo_mensaje_prueba;

        if (! $record || ! $this->isConfigured || $record->ultima_prueba_at) {
            return;
        }

        $result = $service->testConnection($record);
        $this->connectionStatus = $result['status'] ?? null;
        $this->connectionMessage = $result['message'] ?? null;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->isAdminUser());
    }
}
