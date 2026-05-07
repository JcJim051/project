<?php

namespace App\Filament\Pages;

use App\Models\DriveOAuthSetting;
use App\Services\GoogleDriveService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class DriveOAuthSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationGroup = 'Configuracion';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Drive OAuth';

    protected static ?string $navigationLabel = 'Drive OAuth';

    protected static ?string $slug = 'drive-oauth-settings';

    protected static string $view = 'filament.pages.drive-oauth-settings';

    public ?array $data = [];

    public function mount(GoogleDriveService $driveService): void
    {
        $setting = DriveOAuthSetting::query()->latest('id')->first();
        $active = $driveService->oauthCredentials();

        $this->form->fill([
            'client_id' => $active['client_id'] ?? ($setting?->client_id ?? ''),
            'client_secret' => $active['client_secret'] ?? ($setting?->client_secret ?? ''),
            'redirect_uri' => $active['redirect'] ?? ($setting?->redirect_uri ?? ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Textarea::make('client_id')
                    ->label('Client ID')
                    ->required()
                    ->rows(2)
                    ->maxLength(4000),
                Textarea::make('client_secret')
                    ->label('Client Secret')
                    ->required()
                    ->rows(2)
                    ->maxLength(4000),
                TextInput::make('redirect_uri')
                    ->label('Redirect URI')
                    ->required()
                    ->url()
                    ->maxLength(1000),
            ]);
    }

    public function save(GoogleDriveService $driveService): void
    {
        $payload = $this->form->getState();

        $setting = DriveOAuthSetting::query()->latest('id')->first() ?: new DriveOAuthSetting();
        $setting->fill($payload);
        $setting->updated_by = auth()->id();
        $setting->save();

        $driveService->forgetCredentialCache();
        $this->forgetAllDriveTokens();

        Notification::make()
            ->title('Credenciales actualizadas')
            ->body('Se guardaron las credenciales OAuth y se limpiaron tokens previos.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reconectar')
                ->label('Reconectar Drive')
                ->icon('heroicon-o-arrow-path')
                ->url(route('drive.auth', ['return' => request()->url()])),
        ];
    }

    private function forgetAllDriveTokens(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files() as $file) {
            if (str_starts_with($file, 'google-drive-token') && str_ends_with($file, '.json')) {
                $disk->delete($file);
            }
        }
    }
}
