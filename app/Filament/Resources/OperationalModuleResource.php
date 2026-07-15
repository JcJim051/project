<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalModuleResource\Pages;
use App\Models\OperationalModule;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationalModuleResource extends Resource
{
    protected static ?string $model = OperationalModule::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $pluralModelLabel = 'Módulos';
    protected static ?string $modelLabel = 'Módulo';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('codigo')->required()->maxLength(20)->unique(ignoreRecord: true),
            TextInput::make('nombre')->required()->maxLength(255),
            TextInput::make('orden')->numeric()->required()->minValue(0),
            Textarea::make('descripcion')->rows(3),
            TextInput::make('color')->maxLength(20),
            TextInput::make('icono')->maxLength(100),
            Toggle::make('activo')->default(true),
            Toggle::make('crea_tareas')->label('Crea tareas')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->columns([
            TextColumn::make('orden')->sortable(),
            TextColumn::make('codigo')->searchable(),
            TextColumn::make('nombre')->searchable()->wrap(),
            IconColumn::make('activo')->boolean(),
            IconColumn::make('crea_tareas')->label('Tareas')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalModules::route('/'),
            'create' => Pages\CreateOperationalModule::route('/create'),
            'edit' => Pages\EditOperationalModule::route('/{record}/edit'),
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
