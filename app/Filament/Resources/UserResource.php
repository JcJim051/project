<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Role;
use App\Models\User;
use App\Services\PlaneProvisioningService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Autenticacion';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('documento')
                    ->label('Documento')
                    ->maxLength(100),
                Placeholder::make('plane_status')
                    ->label('Estado en Plane')
                    ->content(fn (?User $record): string => $record ? match ($record->plane_sync_status) {
                        'linked' => 'Vinculado correctamente',
                        'invited' => 'Invitación enviada',
                        'not_found' => 'No encontrado en Plane',
                        'error' => 'Con novedad de sincronización',
                        default => 'Pendiente de sincronizar',
                    } : 'Se resolverá cuando el usuario sea invitado a Plane.'),
                TextInput::make('plane_user_id')
                    ->label('Plane user id')
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('plane_last_error')
                    ->label('Última novedad Plane')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->label('Contrasena')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                Select::make('roles')
                    ->label('Roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->required()
                    ->minItems(1)
                    ->maxItems(1)
                    ->default(function (): array {
                        $defaultRoleId = Role::query()
                            ->where('slug', 'consulta')
                            ->value('id');

                        return $defaultRoleId ? [(int) $defaultRoleId] : [];
                    })
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('documento')
                    ->label('Documento')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),
                TextColumn::make('plane_sync_status')
                    ->label('Plane')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'linked' => 'Vinculado',
                        'invited' => 'Invitado',
                        'not_found' => 'No encontrado',
                        'error' => 'Con novedad',
                        default => 'Pendiente',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'linked' => 'success',
                        'invited' => 'warning',
                        'not_found' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Rol'),
            ])
            ->actions([
                Tables\Actions\Action::make('retryPlaneInvitation')
                    ->label('Reintentar Plane')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (User $record): bool => $record->hasAnyRole(['formulador', 'estructurador']))
                    ->action(function (User $record): void {
                        $result = app(PlaneProvisioningService::class)->inviteUserToWorkspace($record);
                        $record->refresh();

                        $notification = Notification::make()
                            ->title($result['success'] ? 'Invitación a Plane procesada' : 'No se pudo procesar la invitación a Plane')
                            ->body($record->plane_sync_status === 'linked'
                                ? 'El usuario quedó vinculado correctamente en Plane.'
                                : ($record->plane_last_error ?: ($result['message'] ?? 'No fue posible sincronizar el usuario con Plane.')));

                        if ($result['success']) {
                            $notification->success();
                        } else {
                            $notification->danger();
                        }

                        $notification->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canManageUsersModule());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }
}
