<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EditProfile extends BaseEditProfile
{
    public static function isSimple(): bool
    {
        return false;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Placeholder::make('force_password_alert')
                            ->label('')
                            ->visible(fn (): bool => (bool) auth()->user()?->must_change_password)
                            ->content(new HtmlString(
                                '<div style="padding:10px 12px;border:1px solid #f59e0b;background:#fffbeb;color:#92400e;border-radius:8px;font-size:13px;font-weight:600;">Debes cambiar tu contraseña y cargar una foto de perfil para continuar usando el panel.</div>'
                            )),
                        FileUpload::make('profile_photo_path')
                            ->label('Foto de perfil')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('profile-photos')
                            ->visibility('public')
                            ->maxFiles(1)
                            ->maxParallelUploads(1)
                            ->fetchFileInformation(false)
                            ->previewable()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->extraInputAttributes([
                                'onchange' => "const max=2*1024*1024;const f=this.files?.[0];if(!f)return;const ok=['image/jpeg','image/png','image/webp'];if(!ok.includes(f.type)){alert('Formato no permitido. Usa JPG, PNG o WEBP.');this.value='';return;}if(f.size>max){alert('La imagen supera el máximo permitido (2 MB).');this.value='';}",
                            ])
                            ->helperText('Sube JPG, PNG o WEBP (máx. 2 MB). Los archivos HEIC pueden quedarse cargando y no se admiten en este campo.'),
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        TextInput::make('current_password')
                            ->label('Contraseña actual')
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->dehydrated(false)
                            ->autocomplete('current-password')
                            ->helperText(fn (): string => (bool) auth()->user()?->must_change_password
                                ? 'En primer ingreso no se requiere contraseña actual.'
                                : 'Requerida para cambiar la contraseña.'),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasPhotoInPayload = array_key_exists('profile_photo_path', $data);
        $photoPath = $data['profile_photo_path'] ?? null;
        $currentPassword = (string) ($data['current_password'] ?? '');
        $isChangingPassword = filled($data['password'] ?? null);
        $isFirstAccessForced = (bool) $record->must_change_password;
        $finalPhotoPath = $hasPhotoInPayload ? $photoPath : $record->profile_photo_path;
        unset($data['profile_photo_path']);
        unset($data['current_password']);

        if ($isFirstAccessForced && ! $isChangingPassword) {
            throw ValidationException::withMessages([
                'data.password' => 'Debes definir una nueva contraseña para continuar.',
            ]);
        }

        if ($isFirstAccessForced && blank($finalPhotoPath)) {
            throw ValidationException::withMessages([
                'data.profile_photo_path' => 'Debes cargar una foto de perfil para continuar.',
            ]);
        }

        if ($isChangingPassword && ! $isFirstAccessForced && !Hash::check($currentPassword, (string) $record->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $record->update($data);

        if ($isChangingPassword && (bool) $record->must_change_password && filled($finalPhotoPath)) {
            $record->forceFill([
                'must_change_password' => false,
            ])->save();
        }

        if ($hasPhotoInPayload) {
            $record->forceFill([
                'profile_photo_path' => $photoPath,
            ])->save();
        }

        return $record;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Guardar perfil'),
            $this->getCancelFormAction(),
        ];
    }
}
