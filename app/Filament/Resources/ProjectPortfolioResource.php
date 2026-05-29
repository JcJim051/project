<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectPortfolioResource\Pages;
use App\Models\ExecutionYear;
use App\Models\MunicipioTipo;
use App\Models\PrioridadEntidad;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\RequirementEvidence;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectPortfolioResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Proyectos';
    protected static ?string $navigationLabel = 'Tablero directivo';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction('resumen')
            ->columns([
                TextColumn::make('id_proyecto')->label('ID')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Nombre clave')->searchable()->wrap(),
                TextColumn::make('municipios_display')->label('Municipios')->state(fn (Project $r) => $r->municipios_display ?: '-')->wrap(),
                TextColumn::make('estructurador.name')->label('Estructurador')->searchable(),
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
                TextColumn::make('prioridadEntidad.nombre')->label('Prioridad entidad'),
                TextColumn::make('stage.nombre')->label('Etapa')->badge(),
                TextColumn::make('status.nombre')->label('Estado')->badge(),
                TextColumn::make('executionYears.anio')->label('Años')->badge()->separator(', '),
                TextColumn::make('duracion_meses')->label('Meses'),
                TextColumn::make('poblacion_objetivo')->label('Población')->numeric(),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('project_stage_id')
                    ->label('Etapa')
                    ->options(ProjectStage::query()->orderBy('nombre')->pluck('nombre', 'id')->all()),
                SelectFilter::make('project_status_id')
                    ->label('Estado')
                    ->options(ProjectStatus::query()->orderBy('orden')->pluck('nombre', 'id')->all()),
                SelectFilter::make('prioridad_entidad_id')
                    ->label('Prioridad entidad')
                    ->options(PrioridadEntidad::query()->orderBy('numero')->get()->mapWithKeys(fn ($p) => [$p->id => "{$p->numero} {$p->nombre}"])->all()),
                SelectFilter::make('estructurador_id')
                    ->label('Estructurador')
                    ->options(User::query()->whereHas('roles', fn ($q) => $q->where('slug', 'estructurador'))->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('execution_year_id')
                    ->label('Año ejecución')
                    ->options(ExecutionYear::query()->orderBy('anio')->pluck('anio', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $value): Builder => $q->whereHas('executionYears', fn (Builder $y): Builder => $y->where('execution_years.id', $value))
                    )),
                SelectFilter::make('municipio_tipo_id')
                    ->label('Tipo municipio')
                    ->options(MunicipioTipo::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $value): Builder => $q->whereHas('municipios.tipos', fn (Builder $t): Builder => $t->where('municipio_tipos.id', $value))
                    )),
            ])
            ->actions([
                Action::make('resumen')
                    ->label('Resumen')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->modalHeading('Resumen')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('7xl')
                    ->modalContent(function (Project $record) {
                        $progress = static::buildProgressByGroup($record);

                        return view('filament.resources.project-portfolio-resource.partials.project-summary-card', [
                            'project' => $record->loadMissing(['producto', 'sectores', 'municipios', 'formulador', 'estructurador', 'stage', 'status', 'executionYears', 'bankProfile']),
                            'progress' => $progress,
                        ]);
                    }),
                Action::make('revision')
                    ->label('Revisión')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Project $record): string => ProjectResource::getUrl('review', ['record' => $record])),
            ])
            ->defaultSort('prioridad_estructurador', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectPortfolios::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return (bool) ($u && ($u->isAdminUser() || $u->hasRole('director')));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['municipios', 'prioridadEntidad', 'stage', 'status', 'executionYears', 'estructurador'])
            ->orderByRaw('prioridad_estructurador IS NULL')
            ->orderBy('prioridad_estructurador');
    }

    private static function buildProgressByGroup(Project $project): array
    {
        $project->loadMissing('sectores');
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->get(['requirements.id', 'requirements.carpeta', 'requirements.sector']);
        $requirements = static::filterSectorial($requirements, $project);

        $total = $requirements->count();
        $evidenceIds = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->distinct()
            ->pluck('requirement_id')
            ->all();

        $doneSet = array_fill_keys($evidenceIds, true);

        $groups = $requirements->groupBy(function ($req) {
            $folder = (string) ($req->carpeta ?: 'Sin carpeta');
            return static::detectTopGroupCode($folder) ?? '99';
        });

        $labels = [
            '01' => '01 Formulación',
            '02' => '02 Presupuesto',
            '03' => '03 Certificaciones',
            '04' => '04 Licencias y Permisos',
            '05' => '05 Estudios y Diseños',
            '99' => 'Otros',
        ];

        $items = [];
        foreach ($labels as $code => $label) {
            $groupReqs = $groups->get($code, collect());
            if ($groupReqs->isEmpty()) {
                continue;
            }

            $done = $groupReqs->filter(fn ($r) => isset($doneSet[$r->id]))->count();
            $groupTotal = $groupReqs->count();
            $items[] = [
                'code' => $code,
                'label' => $label,
                'done' => $done,
                'total' => $groupTotal,
                'percent' => $groupTotal > 0 ? (int) round(($done / $groupTotal) * 100) : 0,
            ];
        }

        $doneTotal = count($doneSet);

        return [
            'overall_done' => $doneTotal,
            'overall_total' => $total,
            'overall_percent' => $total > 0 ? (int) round(($doneTotal / $total) * 100) : 0,
            'groups' => $items,
        ];
    }

    private static function filterSectorial(Collection $requirements, Project $project): Collection
    {
        $sectorNames = $project->sectores
            ->pluck('nombre')
            ->map(fn ($name) => static::normalizeSector($name))
            ->filter()
            ->all();

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = static::normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = static::normalizeSector($req->sector);
                if ($reqSector === '') {
                    return true;
                }

                return in_array($reqSector, $sectorNames, true);
            }

            return true;
        })->values();
    }

    private static function normalizeSector(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private static function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*(\d{1,2})\b/u', $folder, $m)) {
            return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(Str::ascii($folder));
        if (str_contains($normalized, 'formulacion')) return '01';
        if (str_contains($normalized, 'presupuesto')) return '02';
        if (str_contains($normalized, 'certificacion')) return '03';
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) return '04';
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) return '05';

        return null;
    }
}
