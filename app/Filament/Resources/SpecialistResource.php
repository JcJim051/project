<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpecialistResource\Pages;
use App\Models\Specialist;
use App\Services\PlaneProvisioningService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpecialistResource extends Resource
{
    protected static ?string $model = Specialist::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $pluralModelLabel = 'Especialistas';
    protected static ?string $modelLabel = 'Especialista';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')->required()->maxLength(255),
            TextInput::make('correo')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('documento')->maxLength(100),
            TextInput::make('telefono')->maxLength(50),
            TextInput::make('especialidad')->maxLength(255)->helperText('Ejemplo: Hidráulico, Estructural, Geotecnia, Ambiental.'),
            Textarea::make('notas')->rows(3)->columnSpanFull(),
            Toggle::make('activo')->default(true),
            Placeholder::make('plane_status')
                ->label('Estado en Plane')
                ->content(fn (?Specialist $record): string => $record ? match ($record->plane_sync_status) {
                    'linked' => 'Vinculado correctamente',
                    'invited' => 'Invitación enviada',
                    'not_found' => 'No encontrado en Plane',
                    'error' => 'Con novedad de sincronización',
                    default => 'Pendiente de sincronizar',
                } : 'Se resolverá cuando el especialista se use en una sincronización hacia Plane.'),
            TextInput::make('plane_user_id')->label('Plane user id')->disabled()->dehydrated(false),
            Textarea::make('plane_last_error')->label('Última novedad Plane')->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('nombre')->columns([
            TextColumn::make('nombre')->searchable()->sortable(),
            TextColumn::make('especialidad')->label('Especialidad')->searchable()->toggleable(),
            TextColumn::make('correo')->searchable()->copyable(),
            TextColumn::make('documento')->label('Documento')->searchable()->toggleable(),
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
            TextColumn::make('plane_user_id')->label('Plane user id')->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('activo')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('retryPlaneLink')
                ->label('Reintentar vínculo')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (Specialist $record): bool => $record->activo)
                ->action(function (Specialist $record): void {
                    $result = app(PlaneProvisioningService::class)->inviteSpecialistToWorkspace($record);
                    $record->refresh();

                    $notification = Notification::make()
                        ->title($result['success'] ? 'Vínculo con Plane actualizado' : 'No se pudo reintentar el vínculo')
                        ->body($record->plane_sync_status === 'linked'
                            ? 'El especialista quedó vinculado correctamente en Plane.'
                            : ($record->plane_last_error ?: ($result['message'] ?? 'No fue posible resolver el especialista en Plane.')));

                    if ($result['success']) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();
                }),
            Tables\Actions\EditAction::make(),
        ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpecialists::route('/'),
            'create' => Pages\CreateSpecialist::route('/create'),
            'edit' => Pages\EditSpecialist::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->canManageDirectorCatalogs());
    }

    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return static::canViewAny(); }
}
