<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectWorkflowStageResource\Pages;
use App\Filament\Resources\ProjectWorkflowStageResource\RelationManagers;
use App\Models\ProjectWorkflowStage;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProjectWorkflowStageResource extends Resource
{
    protected static ?string $model = ProjectWorkflowStage::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Macroetapa';

    protected static ?string $pluralModelLabel = 'Macroetapas del flujo';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos generales')
                ->description('Configure una macroetapa para una sola fuente de financiación.')
                ->schema([
                    Select::make('funding_source')
                        ->label('Fuente')
                        ->options(['sgr' => 'SGR', 'propios' => 'Recursos propios'])
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                    TextInput::make('sort_order')->label('Orden')->numeric()->required(),
                    Toggle::make('is_optional')->label('Opcional'),
                    Select::make('optional_rule')
                        ->label('Regla de aplicabilidad')
                        ->options(['multiple_execution_years' => 'Solo proyectos con varias vigencias'])
                        ->nullable(),
                    Toggle::make('is_active')->label('Activa')->default(true),
                ])
                ->columns(2),
            Section::make('Configuración avanzada')
                ->description('El identificador técnico se genera automáticamente desde el nombre.')
                ->schema([
                    TextInput::make('slug')
                        ->label('Identificador técnico')
                        ->required()
                        ->maxLength(255),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('funding_source')->label('Fuente')->badge(),
            TextColumn::make('name')->label('Macroetapa')->searchable()->sortable(),
            TextColumn::make('sort_order')->label('Orden')->sortable(),
            TextColumn::make('optional_rule')->label('Aplicabilidad')->placeholder('Siempre'),
            ToggleColumn::make('is_active')->label('Activa'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectWorkflowStages::route('/'),
            'create' => Pages\CreateProjectWorkflowStage::route('/create'),
            'edit' => Pages\EditProjectWorkflowStage::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StepsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canManageParametrizacion();
    }
}
