<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalCycleResource\Pages;
use App\Models\OperationalCycle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperationalCycleResource extends Resource
{
    protected static ?string $model = OperationalCycle::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $modelLabel = 'Ciclo';
    protected static ?string $pluralModelLabel = 'Ciclos';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('codigo')->required()->unique(ignoreRecord: true)->maxLength(80),
            Forms\Components\TextInput::make('nombre')->required()->maxLength(255),
            Forms\Components\Textarea::make('descripcion')->columnSpanFull(),
            Forms\Components\TextInput::make('orden')->numeric()->required()->minValue(0)->default(0),
            Forms\Components\Select::make('anchor_type')->label('Origen de fechas')->options([
                'project_created_at' => 'Relativo a creación del proyecto',
                'fixed_date' => 'Fechas fijas',
            ])->default('project_created_at')->required()->live()->native(false),
            Forms\Components\TextInput::make('start_offset_days')->label('Días desde la creación')->numeric()->default(0)
                ->visible(fn ($get) => $get('anchor_type') === 'project_created_at'),
            Forms\Components\TextInput::make('duration_days')->label('Duración (días)')->numeric()->minValue(1)->default(14)
                ->visible(fn ($get) => $get('anchor_type') === 'project_created_at'),
            Forms\Components\DatePicker::make('fixed_start_date')->label('Inicio fijo')->visible(fn ($get) => $get('anchor_type') === 'fixed_date'),
            Forms\Components\DatePicker::make('fixed_end_date')->label('Fin fijo')->visible(fn ($get) => $get('anchor_type') === 'fixed_date')->afterOrEqual('fixed_start_date'),
            Forms\Components\Select::make('owner_role')->label('Responsable del ciclo')->options([
                'estructurador' => 'Estructurador', 'formulador' => 'Formulador', 'apoyo_ambiental' => 'Apoyo ambiental',
            ])->default('estructurador')->required()->native(false),
            Forms\Components\TextInput::make('timezone')->label('Zona horaria')->default('America/Bogota')->required(),
            Forms\Components\Toggle::make('activo')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('orden')->columns([
            Tables\Columns\TextColumn::make('orden')->sortable(),
            Tables\Columns\TextColumn::make('codigo')->searchable(),
            Tables\Columns\TextColumn::make('nombre')->searchable(),
            Tables\Columns\TextColumn::make('anchor_type')->label('Fechas')->formatStateUsing(fn ($state) => $state === 'fixed_date' ? 'Fijas' : 'Desde proyecto')->badge(),
            Tables\Columns\TextColumn::make('duration_days')->label('Días'),
            Tables\Columns\IconColumn::make('activo')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => Pages\ListOperationalCycles::route('/'), 'create' => Pages\CreateOperationalCycle::route('/create'), 'edit' => Pages\EditOperationalCycle::route('/{record}/edit')]; }
    public static function canViewAny(): bool { return (bool) (auth()->user()?->isAdminUser()); }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit($record): bool { return static::canViewAny(); }
    public static function canDelete($record): bool { return false; }
}
