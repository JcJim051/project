<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectStatusResource\Pages;
use App\Models\ProjectStatus;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectStatusResource extends Resource
{
    protected static ?string $model = ProjectStatus::class;
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Parametrizacion';
    protected static ?string $pluralModelLabel = 'Estados del proyecto';
    protected static ?int $navigationSort = 15;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('orden')->numeric()->required()->minValue(0),
            Toggle::make('manual_allowed')->label('Cambio manual permitido')->default(true),
            Toggle::make('activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->columns([
            TextColumn::make('orden')->sortable(),
            TextColumn::make('nombre')->searchable(),
            IconColumn::make('manual_allowed')->label('Manual')->boolean(),
            IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectStatuses::route('/'),
            'create' => Pages\CreateProjectStatus::route('/create'),
            'edit' => Pages\EditProjectStatus::route('/{record}/edit'),
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

