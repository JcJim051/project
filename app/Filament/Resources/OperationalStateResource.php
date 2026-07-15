<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalStateResource\Pages;
use App\Models\OperationalState;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationalStateResource extends Resource
{
    protected static ?string $model = OperationalState::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $pluralModelLabel = 'Estados';
    protected static ?string $modelLabel = 'Estado';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('codigo')->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('nombre')->required()->maxLength(255),
            TextInput::make('orden')->numeric()->required()->minValue(0),
            TextInput::make('color')->maxLength(20),
            TextInput::make('equivalente_plane')->label('Equivalente en Plane')->maxLength(255),
            Toggle::make('activo')->default(true),
            Toggle::make('es_final')->label('Es final')->default(false),
            Toggle::make('es_bloqueante')->label('Es bloqueante')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->columns([
            TextColumn::make('orden')->sortable(),
            TextColumn::make('codigo')->searchable(),
            TextColumn::make('nombre')->searchable()->wrap(),
            TextColumn::make('equivalente_plane')->label('Plane')->toggleable(),
            IconColumn::make('activo')->boolean(),
            IconColumn::make('es_final')->label('Final')->boolean(),
            IconColumn::make('es_bloqueante')->label('Bloq.')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalStates::route('/'),
            'create' => Pages\CreateOperationalState::route('/create'),
            'edit' => Pages\EditOperationalState::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u && $u->isAdminUser());
    }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return static::canViewAny(); }
}
