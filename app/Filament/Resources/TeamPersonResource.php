<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamPersonResource\Pages;
use App\Models\MeetingPerson;
use App\Services\MeetingAttendanceService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeamPersonResource extends Resource
{
    protected static ?string $model = MeetingPerson::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Reuniones';
    protected static ?string $modelLabel = 'Persona del equipo';
    protected static ?string $pluralModelLabel = 'Personas del equipo';
    protected static ?int $navigationSort = 3;

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        app(MeetingAttendanceService::class)->syncInternalPeopleDirectory();

        return parent::getEloquentQuery()->whereIn('person_kind', ['internal', 'mixed']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('full_name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('document_number')->label('Documento')->searchable(),
                TextColumn::make('internal_source_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'user' => 'Usuario',
                        'specialist' => 'Especialista',
                        'profesional_ambiental' => 'Profesional ambiental',
                        default => 'Interna',
                    }),
                TextColumn::make('phone')->label('Teléfono'),
                TextColumn::make('email_or_address')->label('Correo / dirección')->limit(40),
                TextColumn::make('attendance_entries_count')->label('Asistencias')->counts('attendanceEntries'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewPerson')
                    ->label('Ver ficha')
                    ->url(fn (MeetingPerson $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeamPeople::route('/'),
            'view' => Pages\ViewTeamPerson::route('/{record}/view'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isAdminUser() || $user->hasRole('director')));
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
