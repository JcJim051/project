<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Illuminate\Http\Request;

class RequirementCrudController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $perPage = (int) $request->integer('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString()) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['id', 'source_id', 'carpeta', 'nombre_documento', 'requiere_check', 'visible', 'updated_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $requirements = Requirement::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->input('q'));
                $query->where(function ($inner) use ($q) {
                    $inner->where('texto', 'like', '%' . $q . '%')
                        ->orWhere('requisito', 'like', '%' . $q . '%')
                        ->orWhere('nombre_documento', 'like', '%' . $q . '%')
                        ->orWhere('carpeta', 'like', '%' . $q . '%')
                        ->orWhere('codigo_interno', 'like', '%' . $q . '%')
                        ->orWhere('source_id', 'like', '%' . $q . '%');
                });
            })
            ->when($request->filled('visible'), function ($query) use ($request) {
                $visible = $request->input('visible');
                if (in_array($visible, ['0', '1'], true)) {
                    $query->where('visible', (bool) $visible);
                }
            })
            ->when($request->filled('requiere_check'), function ($query) use ($request) {
                $check = strtoupper(trim((string) $request->input('requiere_check')));
                if (in_array($check, ['SI', 'NO'], true)) {
                    $query->whereRaw('UPPER(requiere_check) = ?', [$check]);
                }
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('requirements.crud.index', compact('requirements'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('requirements.crud.form', ['requirement' => new Requirement()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        Requirement::create($data);

        return redirect()->route('requirements.crud.index')->with('status', 'Requisito creado.');
    }

    public function edit(Requirement $requirement)
    {
        $this->authorizeAdmin();

        return view('requirements.crud.form', compact('requirement'));
    }

    public function update(Request $request, Requirement $requirement)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $requirement->update($data);

        return redirect()->route('requirements.crud.index')->with('status', 'Requisito actualizado.');
    }

    public function toggleCheck(Request $request, Requirement $requirement)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'requiere_check' => ['required', 'in:SI,NO'],
        ]);

        $requirement->update([
            'requiere_check' => $data['requiere_check'],
        ]);

        return redirect()->route('requirements.crud.index')->with('status', 'Requisito actualizado.');
    }

    public function toggleVisible(Request $request, Requirement $requirement)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'visible' => ['required', 'in:0,1'],
        ]);

        $requirement->update([
            'visible' => (bool) $data['visible'],
        ]);

        return redirect()->route('requirements.crud.index')->with('status', 'Requisito actualizado.');
    }

    public function destroy(Requirement $requirement)
    {
        $this->authorizeAdmin();

        $requirement->delete();

        return redirect()->route('requirements.crud.index')->with('status', 'Requisito eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'source_id' => ['nullable', 'integer'],
            'codigo_norma' => ['nullable', 'string', 'max:255'],
            'codigo_interno' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:requirements,id'],
            'texto' => ['nullable', 'string'],
            'sector' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
            'requiere_check' => ['nullable', 'string', 'max:5'],
            'orden' => ['nullable', 'string', 'max:255'],
            'literal' => ['nullable', 'string', 'max:255'],
            'nombre_documento' => ['nullable', 'string', 'max:255'],
            'carpeta' => ['nullable', 'string', 'max:255'],
            'origen' => ['nullable', 'string', 'max:255'],
            'visible' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (!$user || (!$user->is_admin && !$user->hasRole('admin'))) {
            abort(403);
        }
    }
}
