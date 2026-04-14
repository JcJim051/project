<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function show(Project $project)
    {
        $project->load('sectores');
        $requirements = Requirement::query()
            ->where('visible', true)
            ->orderBy('carpeta')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        $requirements = $this->filterSectorial($requirements, $project);

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
            ->map(function ($items) {
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

                return $groups;
            });

        return view('checklist.show', [
            'project' => $project,
            'requirements' => $grouped,
            'applied' => $applied,
            'totalsByFolder' => $totalsByFolder,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'aplica' => ['array'],
            'aplica.*' => ['integer', 'exists:requirements,id'],
        ]);

        $ids = $data['aplica'] ?? [];
        $project->requisitos()->sync($ids);

        return redirect()->route('projects.checklist', $project)->with('status', 'Checklist actualizado.');
    }

    private function filterSectorial($requirements, Project $project)
    {
        $sectorNames = $project->sectores
            ->pluck('nombre')
            ->map(fn ($name) => $this->normalizeSector($name))
            ->filter()
            ->all();

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return true;
                }
                return in_array($reqSector, $sectorNames, true);
            }
            return true;
        })->values();
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
}
