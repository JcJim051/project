<?php

namespace App\Services;

use App\Models\ProjectWorkflowStepRequirement;
use App\Models\Requirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequirementCloneService
{
    public function cloneForWorkflow(Requirement $source, string $name): Requirement
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Escribe el nombre del nuevo requisito.',
            ]);
        }

        $source->loadMissing('workflowStepLinks');
        if ($source->workflowStepLinks->isEmpty()) {
            throw ValidationException::withMessages([
                'name' => 'Este requisito no está vinculado a un elemento del flujo posterior.',
            ]);
        }

        $duplicateExists = Requirement::query()
            ->where('carpeta', $source->carpeta)
            ->where('origen', $source->origen)
            ->whereRaw('LOWER(nombre_documento) = ?', [mb_strtolower($name)])
            ->exists();
        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un requisito con ese nombre en la misma ubicación.',
            ]);
        }

        return DB::transaction(function () use ($source, $name): Requirement {
            $clone = $source->replicate();
            $clone->forceFill([
                'source_id' => null,
                'codigo_interno' => null,
                'numeracion' => null,
                'nombre_documento' => $name,
                'texto' => $name,
                'requisito' => $name,
                'orden' => $this->nextRequirementOrder($source),
            ])->save();

            foreach ($source->workflowStepLinks as $link) {
                ProjectWorkflowStepRequirement::query()->create([
                    'step_id' => $link->step_id,
                    'requirement_id' => $clone->id,
                    'is_required' => $link->is_required,
                    'sort_order' => ((int) ProjectWorkflowStepRequirement::query()
                        ->where('step_id', $link->step_id)
                        ->max('sort_order')) + 1,
                ]);
            }

            return $clone->load('workflowStepLinks.step.stage');
        });
    }

    private function nextRequirementOrder(Requirement $source): string
    {
        $maxOrder = Requirement::query()
            ->where('carpeta', $source->carpeta)
            ->pluck('orden')
            ->map(fn ($order): int => is_numeric($order) ? (int) $order : 0)
            ->max() ?? 0;

        return (string) ($maxOrder + 1);
    }
}
