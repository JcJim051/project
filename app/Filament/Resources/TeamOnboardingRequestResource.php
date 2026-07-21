<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamOnboardingRequestResource\Pages;
use App\Models\TeamOnboardingRequest;
use App\Services\TeamOnboardingService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamOnboardingRequestResource extends Resource
{
    protected static ?string $model = TeamOnboardingRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Caracterización de equipo';
    protected static ?string $modelLabel = 'Solicitud de caracterización';
    protected static ?string $pluralModelLabel = 'Solicitudes de caracterización';
    protected static ?int $navigationSort = 2;

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('full_name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('requested_role')
                    ->label('Rol solicitado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'formulador' => 'Formulador',
                        'estructurador' => 'Estructurador',
                        'especialista' => 'Especialista',
                        default => ucfirst($state),
                    }),
                TextColumn::make('document_number')->label('Documento')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('campaign.title')->label('Campaña')->searchable()->limit(30),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('createdUser.plane_sync_status')
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
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')->label('Enviada')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('requested_role')
                    ->label('Rol')
                    ->options([
                        'formulador' => 'Formulador',
                        'estructurador' => 'Estructurador',
                        'especialista' => 'Especialista',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobada',
                        'rejected' => 'Rechazada',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (TeamOnboardingRequest $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar solicitud')
                    ->modalDescription('Se creará el registro correspondiente en la plataforma según el rol solicitado.')
                    ->modalSubmitActionLabel('Sí, aprobar')
                    ->action(function (TeamOnboardingRequest $record): void {
                        try {
                            app(TeamOnboardingService::class)->approveRequest($record, auth()->user());
                            Notification::make()->title('Solicitud aprobada')->success()->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('No se pudo aprobar la solicitud')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TeamOnboardingRequest $record): bool => $record->status === 'pending')
                    ->modalHeading('Rechazar solicitud')
                    ->modalDescription('Esta solicitud quedará marcada como rechazada y no creará ningún registro.')
                    ->modalSubmitActionLabel('Sí, rechazar')
                    ->form([
                        Textarea::make('review_notes')->label('Observación')->rows(3),
                    ])
                    ->action(function (TeamOnboardingRequest $record, array $data): void {
                        app(TeamOnboardingService::class)->rejectRequest($record, auth()->user(), $data['review_notes'] ?? null);
                        Notification::make()->title('Solicitud rechazada')->success()->send();
                    }),
                Tables\Actions\Action::make('viewRequest')
                    ->label('Ver ficha')
                    ->icon('heroicon-o-eye')
                    ->url(fn (TeamOnboardingRequest $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamOnboardingRequests::route('/'),
            'view' => Pages\ViewTeamOnboardingRequest::route('/{record}/view'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdminUser();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
