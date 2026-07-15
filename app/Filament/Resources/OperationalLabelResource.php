<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalLabelResource\Pages;
use App\Models\OperationalLabel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperationalLabelResource extends Resource
{
    protected static ?string $model = OperationalLabel::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $modelLabel = 'Etiqueta';
    protected static ?string $pluralModelLabel = 'Etiquetas';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')->required()->unique(ignoreRecord: true)->maxLength(80),
            Forms\Components\TextInput::make('nombre')->required()->maxLength(255),
            Forms\Components\ColorPicker::make('color')->required()->default('#64748B'),
            Forms\Components\TextInput::make('orden')->numeric()->required()->default(0),
            Forms\Components\Textarea::make('descripcion')->columnSpanFull(),
            Forms\Components\Toggle::make('activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->columns([
            Tables\Columns\TextColumn::make('orden')->sortable(),
            Tables\Columns\ColorColumn::make('color'),
            Tables\Columns\TextColumn::make('codigo')->searchable(),
            Tables\Columns\TextColumn::make('nombre')->searchable(),
            Tables\Columns\IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ListOperationalLabels::route('/'), 'create' => Pages\CreateOperationalLabel::route('/create'), 'edit' => Pages\EditOperationalLabel::route('/{record}/edit')]; }
    public static function canViewAny(): bool { return (bool) (auth()->user()?->isAdminUser()); }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return false; }
}
