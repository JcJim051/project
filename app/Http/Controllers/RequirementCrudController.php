<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Illuminate\Http\Request;

class RequirementCrudController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $requirements = Requirement::query()
            ->orderBy('id')
            ->get();

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
        if (!$user || !$user->is_admin) {
            abort(403);
        }
    }
}
