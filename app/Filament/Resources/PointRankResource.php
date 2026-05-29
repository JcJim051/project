<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PointRankResource\Pages;
use App\Models\PointRank;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PointRankResource extends Resource
{
    protected static ?string $model = PointRank::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Rango';

    protected static ?string $pluralModelLabel = 'Rangos';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('level_order')
                ->label('Orden del nivel')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(99)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->label('Nombre del rango')
                ->required()
                ->maxLength(255),
            TextInput::make('min_points')
                ->label('Puntaje mínimo')
                ->numeric()
                ->required()
                ->minValue(0),
            FileUpload::make('image_path')
                ->label('Imagen del rango')
                ->image()
                ->disk('public')
                ->directory('rank-images')
                ->maxSize(2048)
                ->imageEditor()
                ->helperText('PNG/JPG/WebP. Máximo 2 MB.'),
            Toggle::make('enabled')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('level_order')
            ->columns([
                TextColumn::make('level_order')->label('Nivel')->sortable(),
                ImageColumn::make('image_path')->label('Imagen')->disk('public')->square(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('min_points')->label('Puntaje mínimo')->sortable(),
                IconColumn::make('enabled')->label('Activo')->boolean(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPointRanks::route('/'),
            'create' => Pages\CreatePointRank::route('/create'),
            'edit' => Pages\EditPointRank::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->canManagePointActivities());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->canManagePointActivities());
    }

    public static function canEdit($record): bool
    {
        return static::canCreate();
    }

    public static function canDelete($record): bool
    {
        return static::canCreate();
    }
}
