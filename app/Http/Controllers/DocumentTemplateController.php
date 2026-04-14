<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentTemplateController extends Controller
{
    public function index()
    {
        $templates = DocumentTemplate::orderBy('nombre')->get();

        return view('document_templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'plantillas' => ['required', 'array', 'min:1'],
            'plantillas.*' => ['file', 'mimes:docx'],
        ], [
            'plantillas.required' => 'Debes cargar al menos una plantilla.',
            'plantillas.*.mimes' => 'Solo se permiten archivos Word (.docx).',
        ]);

        foreach ($request->file('plantillas', []) as $file) {
            $originalName = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $nombre = $originalName !== '' ? $originalName : 'Plantilla';
            $safeName = Str::slug($nombre, '_');
            if ($safeName === '') {
                $safeName = 'plantilla_' . Str::random(8);
            }

            $path = "document_templates/{$safeName}.docx";

            $existing = DocumentTemplate::where('nombre', $nombre)->first();
            if ($existing && $existing->ruta_archivo && Storage::exists($existing->ruta_archivo)) {
                Storage::delete($existing->ruta_archivo);
            }

            $stored = Storage::putFileAs('document_templates', $file, "{$safeName}.docx");
            if (!$stored) {
                return back()->withErrors(['plantillas' => 'No se pudo guardar una de las plantillas. Verifica permisos de almacenamiento.']);
            }

            DocumentTemplate::updateOrCreate(
                ['nombre' => $nombre],
                ['ruta_archivo' => $path]
            );
        }

        return redirect()
            ->route('document_templates.index')
            ->with('status', 'Plantillas actualizadas correctamente.');
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        if ($documentTemplate->ruta_archivo && Storage::exists($documentTemplate->ruta_archivo)) {
            Storage::delete($documentTemplate->ruta_archivo);
        }

        $documentTemplate->delete();

        return redirect()
            ->route('document_templates.index')
            ->with('status', 'Plantilla eliminada.');
    }
}
