<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrioridadEntidadResource\Pages;
use App\Models\PrioridadEntidad;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrioridadEntidadResource extends Resource
{
    protected static ?string $model = PrioridadEntidad::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Prioridades entidad';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('numero')->numeric()->required()->minValue(1)->maxValue(99)->unique(ignoreRecord: true),
            TextInput::make('nombre')->required()->maxLength(255),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('numero')->sortable(),
            TextColumn::make('nombre')->searchable(),
            IconColumn::make('activo')->boolean(),
        ])->defaultSort('numero')
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrioridadEntidads::route('/'),
            'create' => Pages\CreatePrioridadEntidad::route('/create'),
            'edit' => Pages\EditPrioridadEntidad::route('/{record}/edit'),
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

