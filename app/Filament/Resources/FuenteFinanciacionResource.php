<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuenteFinanciacionResource\Pages;
use App\Models\FuenteFinanciacion;
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

class FuenteFinanciacionResource extends Resource
{
    protected static ?string $model = FuenteFinanciacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Fuente';

    protected static ?string $pluralModelLabel = 'Fuentes';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos de la fuente')
                ->columns(3)
                ->schema([
                    TextInput::make('codigo')
                        ->label('Cod fuente')
                        ->required()
                        ->maxLength(80),
                    TextInput::make('nombre')
                        ->label('Fuente')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('tipo')
                        ->label('Tipo')
                        ->required()
                        ->maxLength(150),
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
                TextColumn::make('codigo')->label('Cod fuente')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Fuente')->searchable()->wrap(),
                TextColumn::make('tipo')->label('Tipo')->searchable()->wrap(),
                IconColumn::make('activo')->label('Activo')->boolean(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
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
            'index' => Pages\ListFuenteFinanciacions::route('/'),
            'create' => Pages\CreateFuenteFinanciacion::route('/create'),
            'edit' => Pages\EditFuenteFinanciacion::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canManageParametrizacion());
    }
}
