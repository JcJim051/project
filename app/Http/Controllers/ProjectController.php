<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use App\Models\Producto;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Models\Secretaria;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with(['sectores', 'municipios', 'producto', 'formulador', 'estructurador'])
            ->withCount('requisitos')
            ->orderByDesc('created_at')
            ->get();

        $projectIds = $projects->pluck('id')->all();
        $evidencesByProject = RequirementEvidence::query()
            ->whereIn('project_id', $projectIds)
            ->where('in_drive', true)
            ->get()
            ->groupBy('project_id')
            ->map(function ($items) {
                return $items->pluck('requirement_id')->unique()->values();
            });

        foreach ($projects as $project) {
            $total = (int) ($project->requisitos_count ?? 0);
            $done = $evidencesByProject->get($project->id, collect())->count();
            $project->avance = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        }

        $summaries = [];

        $subcarpetasBase = Requirement::query()
            ->select('carpeta', DB::raw('count(*) as total'))
            ->groupBy('carpeta')
            ->pluck('total', 'carpeta');

        $appliedCounts = DB::table('project_requirement')
            ->join('requirements', 'requirements.id', '=', 'project_requirement.requirement_id')
            ->whereIn('project_requirement.project_id', $projectIds)
            ->select(
                'project_requirement.project_id',
                'requirements.carpeta',
                DB::raw('count(*) as total')
            )
            ->groupBy('project_requirement.project_id', 'requirements.carpeta')
            ->get()
            ->groupBy('project_id')
            ->map(function ($items) {
                return $items->pluck('total', 'carpeta');
            });

        foreach ($projects as $project) {
            $counts = $appliedCounts->get($project->id, collect());
            $subcarpetas = [];
            foreach ($subcarpetasBase as $name => $total) {
                $completed = (int) ($counts[$name] ?? 0);
                $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
                $subcarpetas[$name] = [
                    'total' => (int) $total,
                    'done' => $completed,
                    'percent' => $percent,
                ];
            }

            $summaries[$project->id] = [
                'subcarpetas' => $subcarpetas,
            ];
        }

        return view('projects.index', compact('projects', 'summaries'));
    }

    public function create()
    {
        abort_unless(auth()->user()?->canCreateProjects(), 403);

        $sectors = Sector::query()->where('activo', true)->orderBy('codigo')->orderBy('nombre')->get();
        $secretarias = Secretaria::query()->where('activo', true)->orderBy('nombre')->get();
        $municipios = Municipio::query()->where('activo', true)->orderBy('nombre')->get();
        $users = User::orderBy('name')->get();
        $productos = Producto::query()->where('activo', true)->orderBy('codigo')->orderBy('nombre')->get();

        return view('projects.create', compact('sectors', 'secretarias', 'municipios', 'users', 'productos'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->canCreateProjects(), 403);

        $data = $this->validateProject($request);

        if (!empty($data['ruta_drive'])) {
            $driveId = $this->extractDriveFolderId($data['ruta_drive']);
            if (!$driveId) {
                return back()
                    ->withErrors(['ruta_drive' => 'No se pudo extraer el ID de la carpeta de Drive. Verifica el enlace.'])
                    ->withInput();
            }
            $data['drive_folder_id'] = $driveId;
        }

        $data['municipio'] = $this->municipioNames($data['municipio_ids'] ?? []);
        $project = Project::create($data);
        $this->syncProjectSectors($project, $data);
        $this->syncProjectMunicipios($project, $data);

        return redirect()->route('projects.index')->with('status', 'Proyecto creado correctamente.');
    }

    public function edit(Project $project)
    {
        $project->load(['sectores', 'municipios']);
        $sectors = Sector::query()->where('activo', true)->orderBy('codigo')->orderBy('nombre')->get();
        $secretarias = Secretaria::query()->where('activo', true)->orderBy('nombre')->get();
        $municipios = Municipio::query()->where('activo', true)->orderBy('nombre')->get();
        $users = User::orderBy('name')->get();
        $productos = Producto::query()->where('activo', true)->orderBy('codigo')->orderBy('nombre')->get();

        $principalSectorId = optional($project->sectores->first(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false)))->id;
        $secondarySectorIds = $project->sectores
            ->reject(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false))
            ->pluck('id')
            ->values()
            ->all();

        $municipioIds = $project->municipios->pluck('id')->values()->all();

        return view('projects.edit', compact('project', 'sectors', 'secretarias', 'municipios', 'users', 'productos', 'principalSectorId', 'secondarySectorIds', 'municipioIds'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validateProject($request, $project);

        if (!empty($data['ruta_drive'])) {
            $driveId = $this->extractDriveFolderId($data['ruta_drive']);
            if (!$driveId) {
                return back()
                    ->withErrors(['ruta_drive' => 'No se pudo extraer el ID de la carpeta de Drive. Verifica el enlace.'])
                    ->withInput();
            }
            $data['drive_folder_id'] = $driveId;
        } else {
            $data['drive_folder_id'] = null;
        }

        $data['municipio'] = $this->municipioNames($data['municipio_ids'] ?? []);
        $project->update($data);
        $this->syncProjectSectors($project, $data);
        $this->syncProjectMunicipios($project, $data);

        return redirect()->route('projects.index')->with('status', 'Proyecto actualizado correctamente.');
    }

    private function validateProject(Request $request, ?Project $project = null): array
    {
        $idProyectoUnique = Rule::unique('projects', 'id_proyecto');
        if ($project) {
            $idProyectoUnique = $idProyectoUnique->ignore($project->id);
        }

        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'objeto_proyecto' => ['required', 'string', 'max:500'],
            'id_proyecto' => ['required', 'string', 'max:100', $idProyectoUnique],
            'funding_source' => ['required', Rule::in(['sgr', 'propios'])],
            'producto_id' => ['nullable', 'exists:productos,id'],
            'bipin' => ['nullable', 'string', 'max:100'],
            'nombre_clave' => ['nullable', 'string', 'max:255'],
            'municipio_ids' => ['required', 'array', 'min:1'],
            'municipio_ids.*' => ['exists:municipios,id'],
            'secretaria' => ['nullable', 'string', 'max:255'],
            'ruta_drive' => ['nullable', 'string', 'max:500'],
            'formulador_id' => ['nullable', 'exists:users,id'],
            'estructurador_id' => ['nullable', 'exists:users,id'],
            'fecha_creacion' => ['nullable', 'date'],
            'sector_principal_id' => ['required', 'exists:sectors,id'],
            'sectores_secundarios' => ['nullable', 'array'],
            'sectores_secundarios.*' => ['exists:sectors,id', 'different:sector_principal_id'],
        ], [
            'sector_principal_id.required' => 'Debes seleccionar un sector principal.',
            'municipio_ids.required' => 'Debes seleccionar al menos un municipio.',
            'municipio_ids.min' => 'Debes seleccionar al menos un municipio.',
        ]);
    }

    private function syncProjectSectors(Project $project, array $data): void
    {
        $primaryId = (int) $data['sector_principal_id'];
        $secondary = collect($data['sectores_secundarios'] ?? [])->map(fn ($id) => (int) $id);
        $sectorIds = $secondary->push($primaryId)->unique()->values();
        $project->sectores()->sync($sectorIds->all());
        foreach ($sectorIds as $sectorId) {
            $project->sectores()->updateExistingPivot($sectorId, [
                'is_primary' => ((int) $sectorId === $primaryId),
            ]);
        }
    }

    private function syncProjectMunicipios(Project $project, array $data): void
    {
        $project->municipios()->sync(collect($data['municipio_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all());
    }

    private function municipioNames(array $ids): string
    {
        return Municipio::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->implode(', ');
    }

    private function extractDriveFolderId(string $input): ?string
    {
        $input = trim($input);
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


}
