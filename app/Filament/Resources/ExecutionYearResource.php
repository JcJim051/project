<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExecutionYearResource\Pages;
use App\Models\ExecutionYear;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExecutionYearResource extends Resource
{
    protected static ?string $model = ExecutionYear::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Años de ejecución';
    protected static ?int $navigationSort = 16;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('anio')->numeric()->required()->minValue(2000)->maxValue(2100)->unique(ignoreRecord: true),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('anio')->columns([
            TextColumn::make('anio')->sortable(),
            IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExecutionYears::route('/'),
            'create' => Pages\CreateExecutionYear::route('/create'),
            'edit' => Pages\EditExecutionYear::route('/{record}/edit'),
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

