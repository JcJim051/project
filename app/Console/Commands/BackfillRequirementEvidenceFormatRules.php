<?php

namespace App\Console\Commands;

use App\Models\Requirement;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillRequirementEvidenceFormatRules extends Command
{
    protected $signature = 'requirements:backfill-evidence-format-rules
        {--apply : Guarda los cambios en base de datos. Sin esta opción solo reporta}
        {--chunk=200 : Tamaño del lote de procesamiento}';

    protected $description = 'Diagnostica o completa la regla de formato de evidencia para requisitos existentes.';

    public function handle(GoogleDriveService $drive): int
    {
        if (!Schema::hasColumn('requirements', 'evidence_format_rule')) {
            $this->error('La columna evidence_format_rule no existe. Ejecuta primero las migraciones.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $chunk = max(1, (int) $this->option('chunk'));
        $pending = 0;
        $updated = 0;
        $summary = [];

        Requirement::query()
            ->whereNull('evidence_format_rule')
            ->orderBy('id')
            ->chunkById($chunk, function ($requirements) use ($drive, $apply, &$pending, &$updated, &$summary): void {
                foreach ($requirements as $requirement) {
                    $pending++;
                    $rule = $drive->inferEvidenceFormatRule($requirement) ?: Requirement::EVIDENCE_RULE_ANY;
                    $summary[$rule] = ($summary[$rule] ?? 0) + 1;

                    if ($apply) {
                        $requirement->forceFill([
                            'evidence_format_rule' => $rule,
                        ])->save();
                        $updated++;
                    }
                }
            });

        if ($pending === 0) {
            $this->info('No hay requisitos pendientes por parametrizar.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach (Requirement::evidenceFormatRuleOptions() as $rule => $label) {
            if (!isset($summary[$rule])) {
                continue;
            }

            $rows[] = [
                'regla' => $rule,
                'etiqueta' => $label,
                'cantidad' => $summary[$rule],
            ];
        }

        $this->table(['regla', 'etiqueta', 'cantidad'], $rows);

        if ($apply) {
            $this->info("Backfill completado. Requisitos actualizados: {$updated}.");
        } else {
            $this->warn("Diagnóstico completado. Requisitos pendientes: {$pending}. Usa --apply para guardar.");
        }

        return self::SUCCESS;
    }
}
