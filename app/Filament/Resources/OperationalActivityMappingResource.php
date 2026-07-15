<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalActivityMappingResource\Pages;
use App\Models\OperationalActivityMapping;
use App\Models\OperationalActivityType;
use App\Models\OperationalCycle;
use App\Models\OperationalLabel;
use App\Models\OperationalModule;
use App\Models\Requirement;
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

class OperationalActivityMappingResource extends Resource
{
    protected static ?string $model = OperationalActivityMapping::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Capa operativa';
    protected static ?string $navigationLabel = 'Mapeo operativo';
    protected static ?string $pluralModelLabel = 'Mapeo operativo';
    protected static ?string $modelLabel = 'Actividad operativa';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('operational_module_id')
                ->label('Módulo Plane')
                ->options(fn () => OperationalModule::query()->orderBy('orden')->get()->mapWithKeys(fn (OperationalModule $module) => [$module->id => trim($module->codigo . ' ' . $module->nombre)])->all())
                ->required()
                ->searchable(),
            Select::make('source_type')
                ->label('Tipo de origen')
                ->options([
                    'requirement' => 'Requisito',
                    'generic' => 'Actividad base',
                ])
                ->required()
                ->native(false)
                ->live(),
            Select::make('requirement_id')
                ->label('Requisito Orbit')
                ->searchable()
                ->options(fn () => Requirement::query()
                    ->where('visible', true)
                    ->orderBy('carpeta')
                    ->orderByRaw('custom_project_id IS NOT NULL')
                    ->orderBy('orden')
                    ->orderBy('codigo_interno')
                    ->orderBy('nombre_documento')
                    ->get()
                    ->mapWithKeys(function (Requirement $requirement) {
                        $label = trim(($requirement->carpeta ?: 'Sin carpeta') . ' · ' . ($requirement->nombre_documento ?: $requirement->requisito ?: ('Req ' . $requirement->id)));
                        if ($requirement->custom_project_id) {
                            $label .= ' · Manual';
                        }

                        return [$requirement->id => $label];
                    })
                    ->all())
                ->visible(fn ($get) => $get('source_type') === 'requirement')
                ->required(fn ($get) => $get('source_type') === 'requirement'),
            Toggle::make('repeat_per_study')
                ->label('Repetir por estudio')
                ->helperText('Úsalo para actividades base que deben crearse dentro de cada estudio activo.')
                ->visible(fn ($get) => $get('source_type') === 'generic'),
            TextInput::make('titulo_operativo')->label('Título operativo')->required()->maxLength(255),
            Textarea::make('descripcion_operativa')->label('Descripción operativa')->rows(3),
            Select::make('plane_priority')
                ->label('Prioridad Plane')
                ->options([
                    'urgent' => 'Urgente',
                    'high' => 'Alta',
                    'medium' => 'Media',
                    'low' => 'Baja',
                    'none' => 'Sin prioridad',
                ])
                ->default('medium')
                ->required()
                ->native(false),
            Select::make('responsible_type')
                ->label('Responsable lógico')
                ->options([
                    'formulador' => 'Formulador',
                    'estructurador' => 'Estructurador',
                    'apoyo_ambiental' => 'Apoyo ambiental',
                    'especialista_estudio' => 'Especialista del estudio',
                    'sin_responsable' => 'Sin responsable',
                ])
                ->default('sin_responsable')
                ->required()
                ->native(false),
            Select::make('operational_activity_type_id')
                ->label('Tipo de actividad')
                ->options(fn () => OperationalActivityType::query()->where('activo', true)->orderBy('orden')->pluck('nombre', 'id')->all())
                ->searchable()
                ->native(false),
            Select::make('operational_cycle_id')
                ->label('Ciclo')
                ->options(fn () => OperationalCycle::query()->where('activo', true)->orderBy('orden')->pluck('nombre', 'id')->all())
                ->placeholder('Sin ciclo')
                ->searchable()
                ->native(false),
            Select::make('operationalLabels')
                ->label('Etiquetas')
                ->relationship('operationalLabels', 'nombre', modifyQueryUsing: fn ($query) => $query->where('activo', true)->orderBy('orden'))
                ->multiple()
                ->preload()
                ->searchable(),
            Select::make('planned_start_rule')
                ->label('Regla de inicio')
                ->options([
                    'activation' => 'Solo activación (sin Start date)',
                    'cycle_start' => 'Inicio del ciclo',
                    'none' => 'Sin fecha planeada',
                ])
                ->default('none')
                ->required()
                ->native(false),
            TextInput::make('start_offset_days')->label('Desfase de inicio (días)')->numeric()->default(0),
            TextInput::make('default_duration_days')->label('Duración estimada (días)')->numeric()->minValue(1),
            Toggle::make('track_as_kpi')->label('Medir como indicador')->default(true),
            TextInput::make('orden')->numeric()->required()->minValue(0),
            Toggle::make('activo')->default(true),
            Toggle::make('create_automatically')->label('Crear automáticamente')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->modifyQueryUsing(function ($query) {
                $query->with(['operationalModule', 'requirement']);
            })
            ->columns([
                TextColumn::make('operationalModule.nombre')
                    ->label('Módulo')
                    ->formatStateUsing(fn ($state, OperationalActivityMapping $record) => trim(($record->operationalModule?->codigo ?: '') . ' ' . ($state ?: '')))
                    ->sortable(query: function ($query, string $direction) {
                        $query->leftJoin('operational_modules as om_sort', 'om_sort.id', '=', 'operational_activity_mappings.operational_module_id')
                            ->orderBy('om_sort.orden', $direction)
                            ->select('operational_activity_mappings.*');
                    }),
                TextColumn::make('source_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'requirement' ? 'Requisito' : 'Actividad base'),
                TextColumn::make('requirement.nombre_documento')
                    ->label('Requisito / origen')
                    ->formatStateUsing(function ($state, OperationalActivityMapping $record) {
                        if ($record->source_type === 'generic') {
                            return $record->repeat_per_study ? 'Actividad base por estudio' : 'Actividad base';
                        }

                        return $record->requirement?->nombre_documento
                            ?: $record->requirement?->requisito
                            ?: 'Requisito #' . $record->requirement_id;
                    })
                    ->wrap(),
                TextColumn::make('titulo_operativo')->label('Título')->wrap()->searchable(),
                TextColumn::make('plane_priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'urgent' => 'Urgente',
                        'high' => 'Alta',
                        'medium' => 'Media',
                        'low' => 'Baja',
                        default => 'Sin prioridad',
                    }),
                TextColumn::make('responsible_type')
                    ->label('Responsable')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'formulador' => 'Formulador',
                        'estructurador' => 'Estructurador',
                        'apoyo_ambiental' => 'Apoyo ambiental',
                        'especialista_estudio' => 'Especialista del estudio',
                        default => 'Sin responsable',
                    }),
                TextColumn::make('orden')->sortable(),
                IconColumn::make('repeat_per_study')->label('Por estudio')->boolean(),
                IconColumn::make('activo')->boolean(),
                IconColumn::make('create_automatically')->label('Auto')->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('operational_module_id')
                    ->label('Módulo')
                    ->options(fn () => OperationalModule::query()->orderBy('orden')->get()->mapWithKeys(fn (OperationalModule $module) => [$module->id => trim($module->codigo . ' ' . $module->nombre)])->all()),
                Tables\Filters\SelectFilter::make('source_type')
                    ->label('Tipo')
                    ->options([
                        'requirement' => 'Requisito',
                        'generic' => 'Actividad base',
                    ]),
                Tables\Filters\SelectFilter::make('plane_priority')
                    ->label('Prioridad')
                    ->options([
                        'urgent' => 'Urgente',
                        'high' => 'Alta',
                        'medium' => 'Media',
                        'low' => 'Baja',
                        'none' => 'Sin prioridad',
                    ]),
                Tables\Filters\SelectFilter::make('responsible_type')
                    ->label('Responsable')
                    ->options([
                        'formulador' => 'Formulador',
                        'estructurador' => 'Estructurador',
                        'apoyo_ambiental' => 'Apoyo ambiental',
                        'especialista_estudio' => 'Especialista del estudio',
                        'sin_responsable' => 'Sin responsable',
                    ]),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalActivityMappings::route('/'),
            'create' => Pages\CreateOperationalActivityMapping::route('/create'),
            'edit' => Pages\EditOperationalActivityMapping::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->isAdminUser());
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
        return static::canViewAny();
    }
}
