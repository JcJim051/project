<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MetaResource\Pages;
use App\Models\PlanDevelopmentCatalogItem;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MetaResource extends Resource
{
    protected static ?string $model = PlanDevelopmentCatalogItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Meta';

    protected static ?string $pluralModelLabel = 'Metas';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos de la meta')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sector_codigo')
                            ->label('Codigo sector')
                            ->maxLength(30),
                        TextInput::make('sector')
                            ->label('Sector')
                            ->maxLength(255),
                        TextInput::make('pilar_codigo')
                            ->label('Codigo pilar')
                            ->maxLength(30),
                        TextInput::make('pilar')
                            ->label('Pilar')
                            ->maxLength(500),
                        TextInput::make('eje_codigo')
                            ->label('Codigo eje')
                            ->maxLength(30),
                        TextInput::make('eje')
                            ->label('Eje')
                            ->maxLength(500),
                        TextInput::make('linea_codigo')
                            ->label('Codigo linea')
                            ->maxLength(30),
                        TextInput::make('linea')
                            ->label('Linea')
                            ->maxLength(500),
                        TextInput::make('programa_codigo')
                            ->label('Codigo programa')
                            ->maxLength(30),
                        TextInput::make('programa')
                            ->label('Programa')
                            ->maxLength(500),
                        TextInput::make('subprograma_codigo')
                            ->label('Codigo subprograma')
                            ->maxLength(30),
                        TextInput::make('subprograma')
                            ->label('Subprograma')
                            ->maxLength(500),
                        TextInput::make('codigo_meta_plan')
                            ->label('Codigo meta plan')
                            ->required()
                            ->maxLength(40),
                        TextInput::make('nombre_meta_plan')
                            ->label('Nombre meta plan')
                            ->required()
                            ->maxLength(1200)
                            ->columnSpanFull(),
                        Toggle::make('activo')
                            ->label('Activo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo_meta_plan')
                    ->label('Codigo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nombre_meta_plan')
                    ->label('Nombre meta')
                    ->wrap()
                    ->limit(90)
                    ->searchable(),
                TextColumn::make('sector')
                    ->label('Sector')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('programa')
                    ->label('Programa')
                    ->limit(50)
                    ->toggleable(),
                IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('activo')->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('desactivar')
                        ->label('Desactivar')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['activo' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMetas::route('/'),
            'create' => Pages\CreateMeta::route('/create'),
            'edit' => Pages\EditMeta::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canManageParametrizacion());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}

