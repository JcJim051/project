<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfesionalAmbientalResource\Pages;
use App\Models\ProfesionalAmbiental;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfesionalAmbientalResource extends Resource
{
    protected static ?string $model = ProfesionalAmbiental::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Profesionales ambientales';
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')->required()->maxLength(255),
            TextInput::make('correo')->email()->maxLength(255),
            TextInput::make('telefono')->maxLength(50),
            TextInput::make('documento')->maxLength(100),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nombre')->searchable(),
            TextColumn::make('correo')->searchable(),
            TextColumn::make('telefono'),
            TextColumn::make('documento'),
            IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProfesionalAmbientals::route('/'),
            'create' => Pages\CreateProfesionalAmbiental::route('/create'),
            'edit' => Pages\EditProfesionalAmbiental::route('/{record}/edit'),
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

