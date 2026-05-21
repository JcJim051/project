<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class ProjectDocumentController extends Controller
{
    public function index(Project $project)
    {
        $templates = $this->allowedTemplates($project);

        return view('projects.documents', compact('project', 'templates'));
    }

    public function download(Project $project, DocumentTemplate $documentTemplate)
    {
        if (!$this->isTemplateAllowed($project, $documentTemplate)) {
            abort(403, 'La certificación no está marcada en requisitos.');
        }
        $outputPath = $this->generateDocument($project, $documentTemplate);
        $downloadName = $documentTemplate->nombre . '.docx';

        return response()->download($outputPath, $downloadName)->deleteFileAfterSend(true);
    }

    public function downloadZip(Request $request, Project $project)
    {
        $data = $request->validate([
            'templates' => ['required', 'array', 'min:1'],
            'templates.*' => ['integer', 'exists:document_templates,id'],
        ], [
            'templates.required' => 'Selecciona al menos una plantilla.',
        ]);

        $allowed = $this->allowedTemplates($project)->keyBy('id');
        $templates = collect($data['templates'])
            ->map(fn ($id) => $allowed->get($id))
            ->filter()
            ->values();

        if ($templates->isEmpty()) {
            return back()->withErrors(['templates' => 'No hay plantillas válidas para este proyecto.']);
        }
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . '/' . Str::uuid()->toString() . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $generated = [];

        foreach ($templates as $template) {
            $docPath = $this->generateDocument($project, $template);
            $zip->addFile($docPath, $template->nombre . '.docx');
            $generated[] = $docPath;
        }

        $zip->close();

        foreach ($generated as $docPath) {
            if (file_exists($docPath)) {
                unlink($docPath);
            }
        }

        return response()->download($zipPath, 'certificaciones.zip')->deleteFileAfterSend(true);
    }

    private function generateDocument(Project $project, DocumentTemplate $documentTemplate): string
    {
        $templatePath = storage_path('app/' . $documentTemplate->ruta_archivo);
        if (!file_exists($templatePath)) {
            abort(404, 'No se encontró la plantilla.');
        }

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $outputPath = $tmpDir . '/' . Str::uuid()->toString() . '.docx';

        $processor = new TemplateProcessor($templatePath);
        $processor->setMacroChars('{{', '}}');
        $fundingSource = strtolower((string) ($project->funding_source ?? 'sgr'));
        $referenceCode = $fundingSource === 'sgr'
            ? (string) ($project->bipin ?? '')
            : (string) ($project->id_proyecto ?? '');

        $processor->setValue('OBJETO', $project->objeto_proyecto ?? '');
        $processor->setValue('BPIN', $referenceCode);
        $processor->setValue('BPIN', $referenceCode);
        $processor->setValue('ID_PROYECTO', (string) ($project->id_proyecto ?? ''));
        $processor->setValue('FUENTE_RECURSOS', strtoupper($fundingSource));
        $processor->setValue('FORMULADOR', $project->formulador?->name ?? '');
        $processor->setValue('FECHA', $this->formatFechaLarga(now()));
        $processor->saveAs($outputPath);

        return $outputPath;
    }

    private function formatFechaLarga($date): string
    {
        $day = (int) $date->format('j');
        $year = (int) $date->format('Y');
        $monthName = ucfirst($this->monthNameEs((int) $date->format('n')));
        $dayWords = $this->numberToWordsEs($day);
        $yearWords = $this->numberToWordsEs($year);

        return "a los {$dayWords} ({$day}) días del mes de {$monthName} del año {$yearWords} ({$year}).";
    }

    private function monthNameEs(int $month): string
    {
        $names = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        return $names[$month] ?? '';
    }

    private function numberToWordsEs(int $number): string
    {
        $units = [
            0 => 'cero', 1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciseis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidos', 23 => 'veintitres',
            24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiseis',
            27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve',
        ];

        if ($number <= 29) {
            return $units[$number];
        }

        if ($number < 100) {
            $tensMap = [
                30 => 'treinta', 40 => 'cuarenta', 50 => 'cincuenta',
                60 => 'sesenta', 70 => 'setenta', 80 => 'ochenta', 90 => 'noventa',
            ];
            $tens = intdiv($number, 10) * 10;
            $unit = $number % 10;
            if ($unit === 0) {
                return $tensMap[$tens];
            }
            return $tensMap[$tens] . ' y ' . $units[$unit];
        }

        if ($number < 200) {
            if ($number === 100) {
                return 'cien';
            }
            return 'ciento ' . $this->numberToWordsEs($number - 100);
        }

        if ($number < 1000) {
            $hundredsMap = [
                200 => 'doscientos',
                300 => 'trescientos',
                400 => 'cuatrocientos',
                500 => 'quinientos',
                600 => 'seiscientos',
                700 => 'setecientos',
                800 => 'ochocientos',
                900 => 'novecientos',
            ];
            $hundreds = intdiv($number, 100) * 100;
            $rest = $number % 100;
            if ($rest === 0) {
                return $hundredsMap[$hundreds];
            }
            return $hundredsMap[$hundreds] . ' ' . $this->numberToWordsEs($rest);
        }

        if ($number < 2000) {
            if ($number === 1000) {
                return 'mil';
            }
            return 'mil ' . $this->numberToWordsEs($number - 1000);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $rest = $number % 1000;
            $thousandsWords = $this->numberToWordsEs($thousands) . ' mil';
            if ($rest === 0) {
                return $thousandsWords;
            }
            return $thousandsWords . ' ' . $this->numberToWordsEs($rest);
        }

        return (string) $number;
    }

    private function allowedTemplates(Project $project)
    {
        $requirements = $project->requisitos()->get(['requirements.id', 'requirements.nombre_documento', 'requirements.requisito']);
        if ($requirements->isEmpty()) {
            return collect();
        }

        $reqNames = $requirements->mapWithKeys(function ($req) {
            $name = $req->nombre_documento ?: $req->requisito;
            return [$this->normalizeName($name) => true];
        });

        return DocumentTemplate::query()
            ->where(function ($query) {
                $query->whereNull('file_kind')
                    ->orWhere('file_kind', 'docx');
            })
            ->orderBy('nombre')
            ->get()
            ->filter(function ($template) use ($reqNames) {
                return $reqNames->has($this->normalizeName($template->nombre));
            })
            ->values();
    }

    private function isTemplateAllowed(Project $project, DocumentTemplate $documentTemplate): bool
    {
        $allowed = $this->allowedTemplates($project);
        return $allowed->pluck('id')->contains($documentTemplate->id);
    }

    private function normalizeName(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
