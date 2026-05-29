<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Requirement;
use App\Services\ProjectStatusService;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function show(Project $project)
    {
        $project->load('sectores');
        $sectorCatalog = $this->projectSectorCatalog($project);

        $requirements = Requirement::query()
            ->where('visible', true)
            ->orderBy('carpeta')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        $requirements = $this->filterSectorial($requirements, $sectorCatalog['names']);

        $applied = $project->requisitos()->pluck('requirements.id')->all();

        $totalsByFolder = $requirements
            ->groupBy(function ($req) {
                return $req->carpeta ?? 'Sin carpeta';
            })
            ->map(function ($items) use ($applied) {
                $totalCheckable = $items->filter(function ($req) {
                    return strtoupper((string) $req->requiere_check) === 'SI';
                })->count();
                $active = $items->filter(function ($req) use ($applied) {
                    return strtoupper((string) $req->requiere_check) === 'SI'
                        && in_array($req->id, $applied, true);
                })->count();
                return [
                    'total' => $totalCheckable,
                    'active' => $active,
                ];
            });

        $grouped = $requirements
            ->groupBy(function ($req) {
                return $req->carpeta ?? 'Sin carpeta';
            })
            ->map(function ($items, $folderName) {
                $isSectorialFolder = str_contains($this->normalizeSector((string) $folderName), 'documentos sectoriales');
                if (!$isSectorialFolder) {
                    return $this->buildRequirementGroups($items);
                }

                // Prevent mixed parent/child trees across different sectors.
                $groups = [];
                $itemsBySector = $items->groupBy(function ($req) {
                    return $this->normalizeSector($req->sector) ?: 'sin-sector';
                });
                foreach ($itemsBySector as $sectorItems) {
                    foreach ($this->buildRequirementGroups($sectorItems) as $group) {
                        $groups[] = $group;
                    }
                }

                return $groups;
            });

        return view('checklist.show', [
            'project' => $project,
            'requirements' => $grouped,
            'applied' => $applied,
            'totalsByFolder' => $totalsByFolder,
            'sectorCatalog' => $sectorCatalog,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeProjectMutation();

        $data = $request->validate([
            'aplica' => ['array'],
            'aplica.*' => ['integer', 'exists:requirements,id'],
        ]);

        $ids = $data['aplica'] ?? [];
        $project->requisitos()->sync($ids);
        app(ProjectStatusService::class)->setByName($project, 'Formulación y presentación');

        if ($request->boolean('panel_return')) {
            return redirect()
                ->route('filament.admin.resources.projects.checklist', ['record' => $project])
                ->with('status', 'Checklist actualizado.');
        }

        return redirect()->route('projects.checklist', $project)->with('status', 'Checklist actualizado.');
    }

    private function filterSectorial($requirements, array $sectorNames)
    {
        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return false;
                }
                return $this->sectorMatches($reqSector, $sectorNames);
            }
            return true;
        })->values();
    }

    private function sectorMatches(string $reqSector, array $projectSectors): bool
    {
        foreach ($projectSectors as $projectSector) {
            if ($reqSector === $projectSector) {
                return true;
            }

            // Accept inverse wording matches like "recreacion y deporte" vs "deporte y recreacion".
            $reqTokens = collect(explode(' ', str_replace(' y ', ' ', $reqSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();
            $projTokens = collect(explode(' ', str_replace(' y ', ' ', $projectSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();

            if (!empty($reqTokens) && !empty($projTokens)) {
                sort($reqTokens);
                sort($projTokens);
                if ($reqTokens === $projTokens) {
                    return true;
                }
            }

            if (str_contains($reqSector, $projectSector) || str_contains($projectSector, $reqSector)) {
                return true;
            }
        }

        return false;
    }

    private function projectSectorCatalog(Project $project): array
    {
        $primary = $project->sectores->first(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false));
        if (!$primary) {
            $primary = $project->sectores->first();
        }
        $secondary = $project->sectores->filter(fn ($s) => !(bool) ($s->pivot->is_primary ?? false));
        if ($primary) {
            $secondary = $secondary->reject(fn ($s) => (int) $s->id === (int) $primary->id);
        }

        $ordered = collect();
        if ($primary) {
            $ordered->push([
                'name' => $primary->nombre,
                'normalized' => $this->normalizeSector($primary->nombre),
                'is_primary' => true,
            ]);
        }
        foreach ($secondary as $sector) {
            $ordered->push([
                'name' => $sector->nombre,
                'normalized' => $this->normalizeSector($sector->nombre),
                'is_primary' => false,
            ]);
        }

        $ordered = $ordered->filter(fn ($s) => $s['normalized'] !== '')->values();

        return [
            'ordered' => $ordered->all(),
            'names' => $ordered->pluck('normalized')->all(),
        ];
    }

    private function buildRequirementGroups($items): array
    {
        $byId = $items->keyBy('id');
        $groups = [];

        foreach ($items as $req) {
            $parentId = $req->parent_id;
            if ($parentId && $byId->has($parentId)) {
                $parentKey = $parentId;
            } else {
                $parentKey = $req->id;
            }

            if (!isset($groups[$parentKey])) {
                $groups[$parentKey] = [
                    'parent' => $byId->get($parentKey),
                    'children' => [],
                ];
            }

            if ($parentId && $byId->has($parentId)) {
                $groups[$parentKey]['children'][] = $req;
            }
        }

        foreach ($groups as &$group) {
            usort($group['children'], function ($a, $b) {
                return strnatcasecmp((string) $a->orden, (string) $b->orden)
                    ?: strnatcasecmp((string) $a->codigo_interno, (string) $b->codigo_interno);
            });
        }

        return array_values($groups);
    }

    private function normalizeSector(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = \Illuminate\Support\Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function authorizeProjectMutation(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canMutateProjects()) {
            abort(403);
        }
    }
}
