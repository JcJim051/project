<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MunicipioTipoResource\Pages;
use App\Models\MunicipioTipo;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MunicipioTipoResource extends Resource
{
    protected static ?string $model = MunicipioTipo::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Tipos de municipio';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('nombre')->searchable()->sortable(),
            IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMunicipioTipos::route('/'),
            'create' => Pages\CreateMunicipioTipo::route('/create'),
            'edit' => Pages\EditMunicipioTipo::route('/{record}/edit'),
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

