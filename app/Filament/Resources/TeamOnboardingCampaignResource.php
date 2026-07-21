<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamOnboardingCampaignResource\Pages;
use App\Models\TeamOnboardingCampaign;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeamOnboardingCampaignResource extends Resource
{
    protected static ?string $model = TeamOnboardingCampaign::class;
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Caracterización de equipo';
    protected static ?string $modelLabel = 'Campaña QR';
    protected static ?string $pluralModelLabel = 'Campañas QR';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->label('Título')->required()->maxLength(255),
            Textarea::make('description')->label('Descripción')->rows(3),
            Toggle::make('is_active')->label('Token encendido')->default(true),
            DateTimePicker::make('opens_at')->label('Abre en')->seconds(false),
            DateTimePicker::make('expires_at')->label('Expira en')->seconds(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable(),
                TextColumn::make('public_link')
                    ->label('Link público')
                    ->state(fn (TeamOnboardingCampaign $record): string => route('team-onboarding.campaign', $record->public_token))
                    ->copyable()
                    ->copyMessage('Link público copiado')
                    ->copyMessageDuration(1500)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registration_status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Abierta',
                        'scheduled' => 'Programada',
                        'expired' => 'Vencida',
                        'inactive' => 'Apagada',
                        'closed' => 'Cerrada',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'success',
                        'scheduled' => 'warning',
                        'expired' => 'danger',
                        'inactive' => 'gray',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('requests_count')->label('Solicitudes')->counts('requests'),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('viewCampaign')
                    ->label('Ver campaña')
                    ->icon('heroicon-o-eye')
                    ->url(fn (TeamOnboardingCampaign $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make()->label('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionadas'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamOnboardingCampaigns::route('/'),
            'create' => Pages\CreateTeamOnboardingCampaign::route('/create'),
            'edit' => Pages\EditTeamOnboardingCampaign::route('/{record}/edit'),
            'view' => Pages\ViewTeamOnboardingCampaign::route('/{record}/view'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isAdminUser();
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
