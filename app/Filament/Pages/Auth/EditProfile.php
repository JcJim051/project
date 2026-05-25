<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Database\Eloquent\Model;
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
                            ->helperText('Requerida para cambiar la contraseña.'),
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
        unset($data['profile_photo_path']);
        unset($data['current_password']);

        if ($isChangingPassword && !Hash::check($currentPassword, (string) $record->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $record->update($data);

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
