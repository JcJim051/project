<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\ExecutionYear;
use App\Models\PlanDevelopmentCatalogItem;
use App\Models\PrioridadEntidad;
use App\Models\Producto;
use App\Models\ProfesionalAmbiental;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Secretaria;
use App\Models\Sector;
use App\Models\User;
use App\Models\RequirementEvidence;
use App\Services\RequirementProgressService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables;
use Filament\Tables\Table;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Proyectos';

    protected static ?string $modelLabel = 'Proyecto';

    protected static ?string $pluralModelLabel = 'Mis proyectos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos principales')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre clave')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('id_proyecto')
                            ->label('ID proyecto')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        Select::make('funding_source')
                            ->label('Fuente de recursos')
                            ->options([
                                'sgr' => 'SGR',
                                'propios' => 'Recursos propios',
                            ])
                            ->default('sgr')
                            ->required()
                            ->native(false),
                        TextInput::make('attachments_min_percent')
                            ->label('Umbral mínimo para generar carteras (%)')
                            ->numeric()
                            ->default(80)
                            ->minValue(1)
                            ->maxValue(100)
                            ->step(1)
                            ->required(fn (): bool => static::canEditAttachmentThreshold())
                            ->helperText('General por defecto: 80%. Editable solo por Admin, Director o Formulador Maestro.')
                            ->disabled(fn (): bool => !static::canEditAttachmentThreshold())
                            ->dehydrated(fn (): bool => static::canEditAttachmentThreshold()),
                        TextInput::make('valor')
                            ->label('Valor')
                            ->prefix('$')
                            ->placeholder('$ 8.997.160,00')
                            ->inputMode('text')
                            ->dehydrateStateUsing(fn ($state) => static::normalizeMoneyInput($state))
                            ->rule('nullable')
                            ->maxLength(30),
                        TextInput::make('bipin')
                            ->label('BPIN')
                            ->maxLength(100)
                            ->helperText('Opcional. Si está vacío y la fuente es recursos propios, se usará ID proyecto en certificaciones.'),
                        Select::make('municipio_ids')
                            ->label('Municipios')
                            ->options(fn (): array => static::activeMunicipioOptions())
                            ->getOptionLabelsUsing(fn (array $values): array => Municipio::query()
                                ->whereIn('id', $values)
                                ->orderBy('nombre')
                                ->pluck('nombre', 'id')
                                ->all())
                            ->multiple()
                            ->required()
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->live(),
                        Placeholder::make('municipio_tipos_hint')
                            ->label('Tipología de municipios seleccionados')
                            ->content(fn (Get $get): HtmlString => new HtmlString(static::municipioTypesHintHtml($get('municipio_ids') ?? [])))
                            ->columnSpan(2),
                        Select::make('secretaria')
                            ->label('Secretaria')
                            ->options(fn (): array => static::activeSecretariaOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => Secretaria::query()->where('nombre', $value)->value('nombre') ?: $value)
                            ->searchable()
                            ->preload()
                            ->native(false),
                        DatePicker::make('fecha_creacion')
                            ->label('Fecha creacion'),
                        Select::make('formulador_id')
                            ->label('Formulador')
                            ->options(fn (): array => static::usersByRole('formulador'))
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->searchable()
                            ->preload(),
                        Select::make('estructurador_id')
                            ->label('Estructurador')
                            ->options(fn (): array => static::usersByRole('estructurador'))
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->searchable()
                            ->preload()
                            ->live(),
                        TextInput::make('prioridad_estructurador')
                            ->label('Tu prioridad')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(9999)
                            ->nullable()
                            ->rule(function (Get $get, ?Project $record) {
                                return function (string $attribute, $value, Closure $fail) use ($get, $record): void {
                                    $prioridad = (int) $value;
                                    $estructuradorId = (int) ($get('estructurador_id') ?? 0);
                                    if ($prioridad <= 0 || $estructuradorId <= 0) {
                                        return;
                                    }

                                    $exists = Project::query()
                                        ->when(
                                            $record?->id,
                                            fn (Builder $q): Builder => $q->where('id', '<>', (int) $record->id)
                                        )
                                        ->where('estructurador_id', $estructuradorId)
                                        ->where('prioridad_estructurador', $prioridad)
                                        ->exists();

                                    if ($exists) {
                                        $fail('Ese estructurador ya tiene esa prioridad.');
                                    }
                                };
                            })
                            ->helperText('Exclusiva por estructurador. El valor 1 es la mayor prioridad.'),
                        Select::make('prioridad_entidad_id')
                            ->label('Prioridad entidad')
                            ->options(fn (): array => static::prioridadEntidadOptions())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('profesional_ambiental_id')
                            ->label('Profesional apoyo ambiental')
                            ->options(fn (): array => static::activeAmbientalOptions())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('project_stage_id')
                            ->label('Etapa')
                            ->options(fn (): array => static::activeStageOptions())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('project_status_id')
                            ->label('Estado')
                            ->options(fn (): array => static::activeStatusOptions())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (): bool => !static::canManageProjectManualStatus())
                            ->dehydrated(fn (): bool => static::canManageProjectManualStatus()),
                        Select::make('execution_year_ids')
                            ->label('Años de ejecución')
                            ->options(fn (): array => static::activeExecutionYearOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('duracion_meses')
                            ->label('Duración (meses)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1200),
                        TextInput::make('poblacion_objetivo')
                            ->label('Población objetivo')
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('objeto_proyecto')
                            ->label('Objeto del proyecto')
                            ->required()
                            ->maxLength(500)
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('sector_principal_id')
                            ->label('Sector principal')
                            ->options(fn (): array => static::activeSectorOptions())
                            ->getOptionLabelUsing(fn ($value): ?string => Sector::find($value)?->nombre_con_codigo)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('producto_id', null))
                            ->preload()
                            ->searchable()
                            ->native(false),
                        Select::make('producto_id')
                            ->label('Producto MGA')
                            ->options(fn (Get $get): array => static::activeProductOptions((int) ($get('sector_principal_id') ?? 0)))
                            ->getOptionLabelUsing(fn ($value): ?string => Producto::find($value)?->nombre_con_codigo)
                            ->disabled(fn (Get $get): bool => blank($get('sector_principal_id')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->helperText('Se filtra por el sector principal seleccionado.'),
                        Select::make('sectores_secundarios')
                            ->label('Sectores secundarios')
                            ->options(fn (): array => static::activeSectorOptions())
                            ->getOptionLabelsUsing(fn (array $values): array => Sector::query()
                                ->whereIn('id', $values)
                                ->orderBy('codigo')
                                ->orderBy('nombre')
                                ->get()
                                ->mapWithKeys(fn (Sector $sector): array => [$sector->id => $sector->nombre_con_codigo])
                                ->all())
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->helperText('Opcional. Se mostrarán después del principal en Documentos Sectoriales.')
                            ->columnSpan(2),
                    ]),
                Section::make('Perfil Banco (Excel)')
                    ->description('Datos iniciales para generar F-PE-23, F-PE-24 y F-PE-25. Todos son opcionales.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('horizonte_anio_0')
                            ->label('Horizonte Año 0')
                            ->numeric()
                            ->inputMode('numeric'),
                        TextInput::make('horizonte_anio_1')
                            ->label('Horizonte Año 1')
                            ->numeric()
                            ->inputMode('numeric'),
                        TextInput::make('horizonte_anio_2')
                            ->label('Horizonte Año 2')
                            ->numeric()
                            ->inputMode('numeric'),
                        TextInput::make('horizonte_anio_3')
                            ->label('Horizonte Año 3')
                            ->numeric()
                            ->inputMode('numeric'),
                        Select::make('tipo_presentacion')
                            ->label('Tipo de presentación')
                            ->options([
                                'programa' => 'Programa',
                                'proyecto' => 'Proyecto',
                            ])
                            ->default('proyecto')
                            ->native(false),
                        Select::make('tipo_tramite')
                            ->label('Tipo de trámite')
                            ->options([
                                'nuevo' => 'Nuevo',
                                'actualizacion' => 'Actualización',
                            ])
                            ->default('actualizacion')
                            ->native(false),
                        TextInput::make('codigo_dependencia')
                            ->label('Código dependencia')
                            ->maxLength(30),
                        TextInput::make('dependencia')
                            ->label('Dependencia')
                            ->maxLength(255),
                        TextInput::make('vigencia')
                            ->label('Vigencia')
                            ->numeric()
                            ->inputMode('numeric'),
                        TextInput::make('proyecto_titulo_override')
                            ->label('Proyecto (override opcional)')
                            ->maxLength(500),
                        TextInput::make('sector_texto_plantilla')
                            ->label('Sector texto plantilla')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Se toma automáticamente del sector principal.'),
                        Select::make('pilar')
                            ->label('Pilar')
                            ->options(fn (): array => static::planDistinctOptions('pilar'))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('eje', null);
                                $set('linea', null);
                                $set('programa', null);
                                $set('subprograma', null);
                                $set('meta_plan_codigo', null);
                                $set('meta_plan_nombre', null);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('eje')
                            ->label('Eje')
                            ->options(fn (Get $get): array => static::planDistinctOptions('eje', ['pilar' => $get('pilar')]))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('linea', null);
                                $set('programa', null);
                                $set('subprograma', null);
                                $set('meta_plan_codigo', null);
                                $set('meta_plan_nombre', null);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('linea')
                            ->label('Línea')
                            ->options(fn (Get $get): array => static::planDistinctOptions('linea', [
                                'pilar' => $get('pilar'),
                                'eje' => $get('eje'),
                            ]))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('programa', null);
                                $set('subprograma', null);
                                $set('meta_plan_codigo', null);
                                $set('meta_plan_nombre', null);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('programa')
                            ->label('Programa')
                            ->options(fn (Get $get): array => static::planDistinctOptions('programa', [
                                'pilar' => $get('pilar'),
                                'eje' => $get('eje'),
                                'linea' => $get('linea'),
                            ]))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('subprograma', null);
                                $set('meta_plan_codigo', null);
                                $set('meta_plan_nombre', null);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('subprograma')
                            ->label('Subprograma')
                            ->options(fn (Get $get): array => static::planDistinctOptions('subprograma', [
                                'pilar' => $get('pilar'),
                                'eje' => $get('eje'),
                                'linea' => $get('linea'),
                                'programa' => $get('programa'),
                            ]))
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('meta_plan_codigo', null);
                                $set('meta_plan_nombre', null);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Select::make('meta_plan_codigo')
                            ->label('Cod meta')
                            ->options(fn (Get $get): array => static::planMetaCodeOptions([
                                'pilar' => $get('pilar'),
                                'eje' => $get('eje'),
                                'linea' => $get('linea'),
                                'programa' => $get('programa'),
                                'subprograma' => $get('subprograma'),
                            ]))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                $set('meta_plan_nombre', static::planMetaNameByCode(
                                    (string) ($get('meta_plan_codigo') ?? ''),
                                    [
                                        'pilar' => $get('pilar'),
                                        'eje' => $get('eje'),
                                        'linea' => $get('linea'),
                                        'programa' => $get('programa'),
                                        'subprograma' => $get('subprograma'),
                                    ]
                                ));
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Textarea::make('meta_plan_nombre')
                            ->label('Meta')
                            ->rows(2)
                            ->maxLength(500)
                            ->readOnly(),
                        Select::make('codigo_fuente')
                            ->label('Cod fuente')
                            ->options(fn (): array => static::activeFuenteOptions())
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                $set('nombre_fuente', static::fuenteNameByCodigo((string) ($get('codigo_fuente') ?? '')));
                            })
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('nombre_fuente')
                            ->label('Fuente')
                            ->readOnly()
                            ->dehydrated()
                            ->maxLength(255),
                        TextInput::make('beneficiarios')
                            ->label('Beneficiarios')
                            ->numeric()
                            ->minValue(0)
                            ->inputMode('numeric'),
                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(2),
                    ]),
                Section::make('Drive')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Select::make('drive_setup_mode')
                            ->label('Configuración carpeta Drive')
                            ->options([
                                'auto' => 'Automática (crear proyecto base en Drive)',
                                'manual' => 'Manual (pegar ruta/ID existente)',
                            ])
                            ->default('auto')
                            ->live()
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->columnSpanFull(),
                        TextInput::make('ruta_drive')
                            ->label('Ruta Drive')
                            ->maxLength(500)
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('drive_setup_mode') === 'manual')
                            ->columnSpanFull()
                            ->helperText('Modo manual: acepta URL de carpeta o solo ID. En modo automático la plataforma creará la estructura base.'),
                        TextInput::make('drive_folder_id')
                            ->label('ID carpeta Drive')
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre clave')
                    ->limit(26)
                    ->wrap()
                    ->description(fn (Project $record): HtmlString|string => static::priorityEntitySummaryHtml($record))
                    ->html()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('municipios_display')
                    ->label('Municipios')
                    ->state(fn (Project $record): string => $record->municipios_display ?: '-')
                    ->limit(20)
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('municipio', 'like', "%{$search}%")
                            ->orWhereHas('municipios', fn (Builder $municipios): Builder => $municipios->where('nombre', 'like', "%{$search}%"));
                    }),
                TextColumn::make('funding_source')
                    ->label('Fuente')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sgr' => 'SGR',
                        'propios' => 'Recursos propios',
                        default => (string) ($state ?: '-'),
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state): string => static::formatCurrencyForTable($state))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('sectores.nombre')
                    ->label('Sectores')
                    ->limit(24)
                    ->wrap()
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('producto.nombre')
                    ->label('Producto MGA')
                    ->state(fn (Project $record): string => $record->producto?->nombre_con_codigo ?: '-')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('formulador.name')
                    ->label('Formulador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estructurador.name')
                    ->label('Estructurador')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prioridad_estructurador')
                    ->label('Tu prioridad')
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : ('P' . $state))
                    ->badge()
                    ->color(function ($state): string {
                        $value = (int) $state;
                        if ($value <= 0) {
                            return 'gray';
                        }
                        if ($value === 1) {
                            return 'danger';
                        }
                        if ($value === 2) {
                            return 'warning';
                        }
                        if ($value === 3) {
                            return 'success';
                        }
                        if ($value <= 5) {
                            return 'info';
                        }
                        return 'gray';
                    })
                    ->sortable(),
                TextColumn::make('prioridadEntidad.nombre')
                    ->label('Prioridad entidad')
                    ->html()
                    ->formatStateUsing(fn ($state, Project $record): HtmlString|string => static::priorityEntityBadgeHtml($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stage.nombre')
                    ->label('Etapa')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status.nombre')
                    ->label('Estado')
                    ->badge(),
                TextColumn::make('avance')
                    ->label('Avance')
                    ->state(function (Project $record): int {
                        $requirements = $record->requisitos()->where('requirements.visible', true)->get();
                        $evidences = RequirementEvidence::query()->where('project_id', $record->id)->get();

                        /** @var RequirementProgressService $progressService */
                        $progressService = app(RequirementProgressService::class);
                        $analysis = $progressService->analyze($requirements, $evidences);

                        return $progressService->buildOverallProgress($requirements, $analysis)['percent'];
                    })
                    ->formatStateUsing(fn (int $state): string => $state . '%')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 80 ? 'success' : ($state >= 40 ? 'warning' : 'danger')),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sectores')
                    ->relationship('sectores', 'nombre')
                    ->label('Sector'),
                SelectFilter::make('project_stage_id')
                    ->label('Etapa')
                    ->relationship('stage', 'nombre'),
                SelectFilter::make('project_status_id')
                    ->label('Estado')
                    ->relationship('status', 'nombre'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('requisitos')
                    ->label('Requisitos')
                    ->icon('heroicon-o-check-badge')
                    ->url(fn (Project $record): string => static::getUrl('checklist', ['record' => $record])),
                Tables\Actions\Action::make('gestionar')
                    ->label('Gestionar')
                    ->icon('heroicon-o-folder')
                    ->url(fn (Project $record): string => static::getUrl('manage', ['record' => $record])),
            ])
            ->recordUrl(fn (Project $record): string => static::getUrl('manage', ['record' => $record]))
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('prioridad_estructurador', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['sectores', 'municipios', 'producto', 'formulador', 'estructurador', 'prioridadEntidad', 'stage', 'status'])
            ->withCount('requisitos')
            ->withCount([
                'evidences as evidencias_validas_count' => function ($query) {
                    $query->select(DB::raw('count(distinct requirement_id)'))
                        ->where('in_drive', true);
                },
            ]);

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Admin, director y formulador maestro pueden ver todo el portafolio.
        if ($user->isAdminUser() || $user->hasAnyRole(['director', 'formulador_maestro'])) {
            return $query->orderByRaw('prioridad_estructurador IS NULL')
                ->orderBy('prioridad_estructurador');
        }

        // El resto de roles operativos solo ve proyectos donde esté asignado.
        return $query->where(function (Builder $scoped) use ($user) {
            $scoped->where('formulador_id', $user->id)
                ->orWhere('estructurador_id', $user->id);
        })->orderByRaw('prioridad_estructurador IS NULL')
            ->orderBy('prioridad_estructurador');
    }


    protected static function usersByRole(string $roleSlug): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('slug', $roleSlug))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function activeSectorOptions(): array
    {
        return Sector::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Sector $sector): array => [$sector->id => $sector->nombre_con_codigo])
            ->all();
    }

    protected static function activeProductOptions(int $sectorId): array
    {
        if ($sectorId <= 0) {
            return [];
        }

        return Producto::query()
            ->where('sector_id', $sectorId)
            ->where('activo', true)
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Producto $producto): array => [$producto->id => $producto->nombre_con_codigo])
            ->all();
    }

    protected static function activeSecretariaOptions(): array
    {
        return Secretaria::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'nombre')
            ->all();
    }

    protected static function prioridadEntidadOptions(): array
    {
        return PrioridadEntidad::query()
            ->where('activo', true)
            ->orderBy('numero')
            ->get()
            ->mapWithKeys(fn (PrioridadEntidad $item): array => [$item->id => "{$item->numero} {$item->nombre}"])
            ->all();
    }

    protected static function activeAmbientalOptions(): array
    {
        return ProfesionalAmbiental::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    protected static function activeStageOptions(): array
    {
        return ProjectStage::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    protected static function activeStatusOptions(): array
    {
        return ProjectStatus::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->pluck('nombre', 'id')
            ->all();
    }

    protected static function activeExecutionYearOptions(): array
    {
        return ExecutionYear::query()
            ->where('activo', true)
            ->orderBy('anio')
            ->pluck('anio', 'id')
            ->all();
    }

    public static function priorityEntityBadgeHtml(Project $record): HtmlString|string
    {
        $priority = $record->prioridadEntidad;

        if (!$priority) {
            return '-';
        }

        return new HtmlString(sprintf(
            '<span style="%s">%s</span>',
            $priority->badgeStyle(),
            e($priority->badgeLabel())
        ));
    }

    public static function priorityEntitySummaryHtml(Project $record): HtmlString|string
    {
        $priority = $record->prioridadEntidad;

        if (!$priority) {
            return 'P. entidad: Sin definir';
        }

        return new HtmlString(sprintf(
            '<span style="%s">%s</span>',
            $priority->badgeStyle(),
            e($priority->summaryLabel())
        ));
    }

    protected static function canManageProjectManualStatus(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isAdminUser() || $user->hasRole('director')));
    }

    protected static function municipioTypesHintHtml(array $municipioIds): string
    {
        $ids = collect($municipioIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return '<span class="text-gray-500">Selecciona municipios para ver tipologías (PEDER, SOMAC, etc.).</span>';
        }

        $municipios = Municipio::query()
            ->with(['tipos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->whereIn('id', $ids->all())
            ->orderBy('nombre')
            ->get();

        if ($municipios->isEmpty()) {
            return '<span class="text-gray-500">Sin tipologías configuradas.</span>';
        }

        $rows = $municipios->map(function (Municipio $municipio): string {
            $chips = $municipio->tipos->map(function ($tipo): string {
                return '<span class="inline-flex items-center rounded-full bg-lime-100 px-2 py-0.5 text-[11px] font-semibold text-lime-800">' . e($tipo->nombre) . '</span>';
            })->implode(' ');
            if ($chips === '') {
                $chips = '<span class="text-gray-400 text-xs">Sin tipología</span>';
            }

            return '<div class="flex flex-wrap items-center gap-2"><span class="text-sm font-medium text-gray-700">'
                . e($municipio->nombre) . '</span>' . $chips . '</div>';
        })->implode('');

        return '<div class="space-y-1">' . $rows . '</div>';
    }

    protected static function activeMunicipioOptions(): array
    {
        return Municipio::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }

    protected static function normalizeMoneyInput($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]/', '', str_replace("\xc2\xa0", ' ', $raw));
        if ($normalized === '' || $normalized === null) {
            return null;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');
        $decimalPos = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);

        if ($decimalPos >= 0) {
            $decimals = substr($normalized, $decimalPos + 1);
            if ($decimals !== '' && strlen($decimals) <= 2 && ctype_digit($decimals)) {
                $integerPart = preg_replace('/[^\d\-]/', '', substr($normalized, 0, $decimalPos));
                if ($integerPart === '' || $integerPart === '-') {
                    $integerPart = '0';
                }

                return $integerPart . '.' . $decimals;
            }
        }

        $integerOnly = preg_replace('/[^\d\-]/', '', $normalized);
        if ($integerOnly === '' || $integerOnly === '-') {
            return null;
        }

        return $integerOnly;
    }

    protected static function formatCurrencyForTable($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '-';
        }

        $normalized = static::normalizeMoneyInput($value);
        if ($normalized === null) {
            return '-';
        }

        return '$ ' . number_format((float) $normalized, 2, ',', '.');
    }

    protected static function activeFuenteOptions(): array
    {
        if (! Schema::hasTable('fuente_financiacions')) {
            return [];
        }

        return FuenteFinanciacion::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get()
            ->mapWithKeys(fn (FuenteFinanciacion $fuente): array => [$fuente->codigo => $fuente->codigo . ' - ' . $fuente->nombre])
            ->all();
    }

    protected static function fuenteNameByCodigo(string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! Schema::hasTable('fuente_financiacions')) {
            return null;
        }

        return FuenteFinanciacion::query()
            ->where('codigo', $codigo)
            ->value('nombre');
    }

    protected static function planDistinctOptions(string $column, array $filters = []): array
    {
        $query = PlanDevelopmentCatalogItem::query()
            ->where('activo', true)
            ->whereNotNull($column)
            ->where($column, '!=', '');

        foreach ($filters as $key => $value) {
            if (! blank($value)) {
                $query->where($key, $value);
            }
        }

        return $query->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }

    protected static function planMetaCodeOptions(array $filters = []): array
    {
        $query = PlanDevelopmentCatalogItem::query()
            ->where('activo', true)
            ->whereNotNull('codigo_meta_plan')
            ->where('codigo_meta_plan', '!=', '');

        foreach ($filters as $key => $value) {
            if (! blank($value)) {
                $query->where($key, $value);
            }
        }

        return $query->orderBy('codigo_meta_plan')
            ->pluck('codigo_meta_plan', 'codigo_meta_plan')
            ->all();
    }

    protected static function planMetaNameByCode(string $code, array $filters = []): ?string
    {
        if (trim($code) === '') {
            return null;
        }

        $query = PlanDevelopmentCatalogItem::query()
            ->where('activo', true)
            ->where('codigo_meta_plan', $code);

        foreach ($filters as $key => $value) {
            if (! blank($value)) {
                $query->where($key, $value);
            }
        }

        return $query->value('nombre_meta_plan');
    }

    public static function extractDriveFolderId(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $input)) {
            return $input;
        }

        if (preg_match('#/folders/([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('#id=([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        if (preg_match('#/drive/folders/([a-zA-Z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'checklist' => Pages\ManageProjectChecklist::route('/{record}/checklist'),
            'manage' => Pages\ManageProject::route('/{record}/manage'),
            'review' => Pages\ReviewProject::route('/{record}/review'),
            'bank' => Pages\ManageProjectBank::route('/{record}/banco'),
            'documents' => Pages\ManageProjectDocuments::route('/{record}/documents'),
            'attachments' => Pages\ManageProjectAttachments::route('/{record}/attachments-pdf'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canAccessPanel() && !$user->isPlanningAimOnlyUser());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canCreateProjects());
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->canMutateProjects());
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->isAdminUser());
    }

    protected static function canEditAttachmentThreshold(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->hasAnyRole(['admin', 'director', 'formulador_maestro']));
    }
}
