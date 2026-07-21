<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeetingAttendanceSessionResource\Pages;
use App\Models\MeetingAttendanceSession;
use App\Services\MeetingAttendanceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeetingAttendanceSessionResource extends Resource
{
    protected static ?string $model = MeetingAttendanceSession::class;
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Reuniones';
    protected static ?string $modelLabel = 'Sesión de asistencia';
    protected static ?string $pluralModelLabel = 'Asistencias a reuniones';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->label('Título')->maxLength(255),
            Textarea::make('objetivo')->label('Objetivo')->rows(3)->required(),
            DatePicker::make('fecha')->label('Fecha')->required(),
            TextInput::make('lugar')->label('Lugar')->maxLength(255),
            TimePicker::make('hora_inicio')->label('Hora de inicio')->seconds(false),
            TimePicker::make('hora_terminacion')->label('Hora de terminación')->seconds(false),
            Toggle::make('is_active')->label('Token encendido')->default(true),
            DateTimePicker::make('opens_at')->label('Abre en')->seconds(false),
            DateTimePicker::make('expires_at')->label('Expira en')->seconds(false),
            TextInput::make('template_version')->label('Versión plantilla')->default(app(MeetingAttendanceService::class)->templateVersion()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable(),
                TextColumn::make('fecha')->label('Fecha')->date('Y-m-d')->sortable(),
                TextColumn::make('lugar')->label('Lugar')->searchable()->limit(30),
                TextColumn::make('public_link')
                    ->label('Link público')
                    ->state(fn (MeetingAttendanceSession $record): string => route('attendance.session', $record->public_token))
                    ->copyable()
                    ->copyMessage('Link público copiado')
                    ->copyMessageDuration(1500)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registration_status')
                    ->label('Estado token')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Abierto',
                        'scheduled' => 'Programado',
                        'expired' => 'Vencido',
                        'inactive' => 'Apagado',
                        'closed' => 'Cerrado',
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
                TextColumn::make('entries_count')->label('Asistentes')->counts('entries'),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSession')
                    ->label('Ver sesión')
                    ->icon('heroicon-o-eye')
                    ->url(fn (MeetingAttendanceSession $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMeetingAttendanceSessions::route('/'),
            'create' => Pages\CreateMeetingAttendanceSession::route('/create'),
            'edit' => Pages\EditMeetingAttendanceSession::route('/{record}/edit'),
            'view' => Pages\ViewMeetingAttendanceSession::route('/{record}/view'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAccessPanel();
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
