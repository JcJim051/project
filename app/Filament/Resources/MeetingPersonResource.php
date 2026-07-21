<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MeetingPersonResource\Pages;
use App\Models\MeetingPerson;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MeetingPersonResource extends Resource
{
    protected static ?string $model = MeetingPerson::class;
    protected static ?string $navigationIcon = 'heroicon-o-identification';
    protected static ?string $navigationGroup = 'Reuniones';
    protected static ?string $modelLabel = 'Persona asistente';
    protected static ?string $pluralModelLabel = 'Directorio de asistentes';
    protected static ?int $navigationSort = 2;

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('full_name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('document_number')->label('Documento')->searchable()->sortable(),
                TextColumn::make('organization_area')->label('Entidad / área')->searchable()->limit(40),
                TextColumn::make('person_kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'internal' => 'Interna',
                        'mixed' => 'Mixta',
                        default => 'Externa',
                    }),
                TextColumn::make('attendance_entries_count')->label('Participaciones')->counts('attendanceEntries'),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('person_kind')
                    ->options([
                        'internal' => 'Interna',
                        'external' => 'Externa',
                        'mixed' => 'Mixta',
                    ]),
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
            'index' => Pages\ListMeetingPeople::route('/'),
            'view' => Pages\ViewMeetingPerson::route('/{record}/view'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAccessPanel();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
