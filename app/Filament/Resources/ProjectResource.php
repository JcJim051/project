<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\PlanDevelopmentCatalogItem;
use App\Models\Producto;
use App\Models\Project;
use App\Models\Secretaria;
use App\Models\Sector;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Database\Eloquent\Builder;
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
                            ->native(false),
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
                            ->preload(),
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
                Section::make('Contenido y Drive')
                    ->collapsible()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Textarea::make('objeto_proyecto')
                            ->label('Objeto del proyecto')
                            ->required()
                            ->maxLength(500)
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('ruta_drive')
                            ->label('Ruta Drive')
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->helperText('Acepta URL de carpeta o solo ID.'),
                        TextInput::make('drive_folder_id')
                            ->label('ID carpeta Drive')
                            ->disabled()
                            ->dehydrated(false),
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre clave')
                    ->limit(34)
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('municipios_display')
                    ->label('Municipios')
                    ->state(fn (Project $record): string => $record->municipios_display ?: '-')
                    ->limit(28)
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
                    ->sortable(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state): string => static::formatCurrencyForTable($state))
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
                TextColumn::make('avance')
                    ->label('Avance')
                    ->state(function (Project $record): int {
                        $total = (int) ($record->requisitos_count ?? 0);
                        $done = (int) ($record->evidencias_validas_count ?? 0);
                        if ($total === 0) {
                            return 0;
                        }

                        return (int) round(($done / $total) * 100);
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
                Tables\Actions\Action::make('banco_excel')
                    ->label('Banco Excel')
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (Project $record): string => static::getUrl('bank', ['record' => $record])),
                Tables\Actions\Action::make('adjuntos_pdf')
                    ->label('Paquete PDF')
                    ->icon('heroicon-o-archive-box')
                    ->url(fn (Project $record): string => static::getUrl('attachments', ['record' => $record])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['sectores', 'municipios', 'producto', 'formulador', 'estructurador'])
            ->withCount('requisitos')
            ->withCount([
                'evidences as evidencias_validas_count' => function ($query) {
                    $query->select(DB::raw('count(distinct requirement_id)'))
                        ->where('in_drive', true);
                },
            ]);
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
            'bank' => Pages\ManageProjectBank::route('/{record}/banco'),
            'attachments' => Pages\ManageProjectAttachments::route('/{record}/attachments-pdf'),
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

        return (bool) ($user && $user->canMutateProjects());
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
}
