<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectWorkflowStepResource\Pages;
use App\Models\ProjectWorkflowStep;
use App\Models\Requirement;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class ProjectWorkflowStepResource extends Resource
{
    protected static ?string $model = ProjectWorkflowStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Parametrizacion';

    protected static ?string $modelLabel = 'Elemento del flujo';

    protected static ?string $pluralModelLabel = 'Elementos del flujo';

    protected static ?int $navigationSort = 6;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema(static::elementFormSchema());
    }

    public static function elementFormSchema(bool $includeStage = true): array
    {
        $generalFields = [];

        if ($includeStage) {
            $generalFields[] = Select::make('stage_id')
                ->label('Macroetapa')
                ->relationship('stage', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => strtoupper($record->funding_source).' · '.$record->name)
                ->searchable()
                ->preload()
                ->required();
        }

        $generalFields = [
            ...$generalFields,
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            TextInput::make('sort_order')->label('Orden')->numeric()->required(),
            Textarea::make('description')->label('Descripción')->columnSpanFull(),
            Toggle::make('is_active')->label('Activo')->default(true),
        ];

        return [
            Section::make('Datos del elemento')
                ->schema($generalFields)
                ->columns(2),
            Repeater::make('requirementLinks')
                ->label('Requisitos del elemento')
                ->relationship()
                ->schema([
                    Select::make('requirement_id')
                        ->label('Requisito')
                        ->options(fn () => Requirement::query()
                            ->where('visible', true)
                            ->orderBy('nombre_documento')
                            ->get()
                            ->mapWithKeys(fn ($requirement) => [
                                $requirement->id => $requirement->nombre_documento
                                    ?: $requirement->requisito
                                    ?: ('Requisito '.$requirement->id),
                            ])->all())
                        ->searchable()
                        ->required(),
                    Toggle::make('is_required')->label('Obligatorio')->default(true),
                    TextInput::make('sort_order')->label('Orden')->numeric()->default(1),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->addActionLabel('Vincular requisito'),
            Section::make('Configuración avanzada')
                ->description('El identificador técnico se genera automáticamente desde el nombre.')
                ->schema([
                    Select::make('completion_rule')
                        ->label('Regla de cumplimiento')
                        ->options(ProjectWorkflowStep::completionRuleOptions())
                        ->placeholder('Requisitos vinculados normalmente')
                        ->helperText('La regla de definitivos obtiene automáticamente las licencias y permisos aplicables del proyecto.'),
                    TextInput::make('slug')
                        ->label('Identificador técnico')
                        ->required()
                        ->maxLength(255),
                ])
                ->collapsed(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('stage.funding_source')->label('Fuente')->badge(),
            TextColumn::make('stage.name')->label('Macroetapa')->searchable(),
            TextColumn::make('name')->label('Elemento')->searchable(),
            TextColumn::make('sort_order')->label('Orden')->sortable(),
            TextColumn::make('requirement_links_count')->counts('requirementLinks')->label('Requisitos'),
            ToggleColumn::make('is_active')->label('Activo'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectWorkflowSteps::route('/'),
            'create' => Pages\CreateProjectWorkflowStep::route('/create'),
            'edit' => Pages\EditProjectWorkflowStep::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canManageParametrizacion();
    }
}
