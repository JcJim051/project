<?php

namespace Tests\Feature;

use App\Models\Requirement;
use App\Services\RequirementCloneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCloneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clones_a_requirement_in_the_same_workflow_location(): void
    {
        $source = Requirement::query()
            ->where('nombre_documento', 'Declaratoria de importancia estratégica')
            ->with('workflowStepLinks.step.stage')
            ->firstOrFail();
        $sourceLink = $source->workflowStepLinks->firstOrFail();

        $clone = app(RequirementCloneService::class)->cloneForWorkflow(
            $source,
            'Concepto técnico de importancia estratégica'
        );
        $cloneLink = $clone->workflowStepLinks->firstOrFail();

        $this->assertNotSame($source->id, $clone->id);
        $this->assertSame('Concepto técnico de importancia estratégica', $clone->nombre_documento);
        $this->assertSame($clone->nombre_documento, $clone->texto);
        $this->assertSame($clone->nombre_documento, $clone->requisito);
        $this->assertSame($source->carpeta, $clone->carpeta);
        $this->assertSame($source->tipo, $clone->tipo);
        $this->assertSame($source->origen, $clone->origen);
        $this->assertSame($source->evidence_format_rule, $clone->evidence_format_rule);
        $this->assertNull($clone->source_id);
        $this->assertNull($clone->codigo_interno);
        $this->assertNull($clone->numeracion);
        $this->assertSame($sourceLink->step_id, $cloneLink->step_id);
        $this->assertSame($sourceLink->is_required, $cloneLink->is_required);
        $this->assertSame('Preparación institucional', $cloneLink->step->stage->name);
        $this->assertCount(0, $clone->evidences);
        $this->assertCount(0, $clone->proyectos);
    }
}
