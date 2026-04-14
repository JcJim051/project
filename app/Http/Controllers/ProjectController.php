<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with(['sectores', 'formulador', 'estructurador'])
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
        $this->ensureSectorsFromRequirements();
        $sectors = Sector::orderBy('nombre')->get();
        $users = User::orderBy('name')->get();

        return view('projects.create', compact('sectors', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'objeto_proyecto' => ['required', 'string', 'max:500'],
            'id_proyecto' => ['required', 'string', 'max:100', 'unique:projects,id_proyecto'],
            'bipin' => ['nullable', 'string', 'max:100'],
            'nombre_clave' => ['nullable', 'string', 'max:255'],
            'municipio' => ['required', 'string', 'max:255'],
            'secretaria' => ['nullable', 'string', 'max:255'],
            'ruta_drive' => ['nullable', 'string', 'max:500'],
            'formulador_id' => ['nullable', 'exists:users,id'],
            'estructurador_id' => ['nullable', 'exists:users,id'],
            'fecha_creacion' => ['nullable', 'date'],
            'sectores' => ['required', 'array', 'min:1'],
            'sectores.*' => ['exists:sectors,id'],
        ], [
            'sectores.required' => 'Debes seleccionar al menos un sector.',
        ]);

        if (!empty($data['ruta_drive'])) {
            $driveId = $this->extractDriveFolderId($data['ruta_drive']);
            if (!$driveId) {
                return back()
                    ->withErrors(['ruta_drive' => 'No se pudo extraer el ID de la carpeta de Drive. Verifica el enlace.'])
                    ->withInput();
            }
            $data['drive_folder_id'] = $driveId;
        }

        $project = Project::create($data);
        $project->sectores()->sync($data['sectores']);

        return redirect()->route('projects.index')->with('status', 'Proyecto creado correctamente.');
    }

    public function edit(Project $project)
    {
        $project->load('sectores');
        $this->ensureSectorsFromRequirements();
        $sectors = Sector::orderBy('nombre')->get();
        $users = User::orderBy('name')->get();

        return view('projects.edit', compact('project', 'sectors', 'users'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'objeto_proyecto' => ['required', 'string', 'max:500'],
            'id_proyecto' => ['required', 'string', 'max:100', Rule::unique('projects', 'id_proyecto')->ignore($project->id)],
            'bipin' => ['nullable', 'string', 'max:100'],
            'nombre_clave' => ['nullable', 'string', 'max:255'],
            'municipio' => ['required', 'string', 'max:255'],
            'secretaria' => ['nullable', 'string', 'max:255'],
            'ruta_drive' => ['nullable', 'string', 'max:500'],
            'formulador_id' => ['nullable', 'exists:users,id'],
            'estructurador_id' => ['nullable', 'exists:users,id'],
            'fecha_creacion' => ['nullable', 'date'],
            'sectores' => ['required', 'array', 'min:1'],
            'sectores.*' => ['exists:sectors,id'],
        ], [
            'sectores.required' => 'Debes seleccionar al menos un sector.',
        ]);

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

        $project->update($data);
        $project->sectores()->sync($data['sectores']);

        return redirect()->route('projects.index')->with('status', 'Proyecto actualizado correctamente.');
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

    private function ensureSectorsFromRequirements(): void
    {
        $sectors = Requirement::query()
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        foreach ($sectors as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            Sector::firstOrCreate(['nombre' => $name]);
        }
    }
}
