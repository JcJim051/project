<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PointActivityResource\Pages;
use App\Models\PointActivity;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PointActivityResource extends Resource
{
    protected static ?string $model = PointActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Actividad de puntaje';

    protected static ?string $pluralModelLabel = 'Actividades de puntaje';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')
                ->label('Código')
                ->required()
                ->alphaDash()
                ->unique(ignoreRecord: true)
                ->maxLength(100),
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Descripción')
                ->rows(2),
            Toggle::make('enabled')
                ->label('Activa')
                ->default(true),
            TextInput::make('points')
                ->label('Puntos')
                ->numeric()
                ->required(),
            Select::make('role_scope')
                ->label('Alcance de rol')
                ->options([
                    'ambos' => 'Formulador y Estructurador',
                    'formulador' => 'Solo Formulador',
                    'estructurador' => 'Solo Estructurador',
                ])
                ->default('ambos')
                ->required()
                ->native(false),
            Select::make('trigger_type')
                ->label('Tipo de disparador')
                ->options([
                    'backend_event' => 'Evento backend',
                    'manual' => 'Manual',
                ])
                ->default('backend_event')
                ->required()
                ->native(false),
            Select::make('uniqueness_scope')
                ->label('Unicidad')
                ->options([
                    'once_per_requirement' => 'Una vez por requisito',
                    'once_per_project' => 'Una vez por proyecto',
                    'once_per_day' => 'Una vez por día',
                    'once_per_season' => 'Una vez por vigencia',
                ])
                ->default('once_per_season')
                ->required()
                ->native(false),
            Select::make('season_mode')
                ->label('Temporada')
                ->options(['annual' => 'Vigencia anual'])
                ->default('annual')
                ->required()
                ->native(false),
            DatePicker::make('effective_from')->label('Vigente desde'),
            DatePicker::make('effective_to')->label('Vigente hasta'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('points')->label('Puntos')->sortable(),
                TextColumn::make('role_scope')->label('Rol')->badge(),
                TextColumn::make('uniqueness_scope')->label('Unicidad')->badge(),
                IconColumn::make('enabled')->label('Activa')->boolean(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
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
            'index' => Pages\ListPointActivities::route('/'),
            'create' => Pages\CreatePointActivity::route('/create'),
            'edit' => Pages\EditPointActivity::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->canAccessPanel());
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
