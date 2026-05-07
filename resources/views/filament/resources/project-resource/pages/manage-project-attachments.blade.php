<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Paquete PDF con adjuntos</h3>
                    <p class="text-xs text-gray-500">
                        Disponible solo al 100%. Genera ZIP versionado (V{N}) y lo sube a Drive en 02 Cargue.
                    </p>
                    <p class="mt-1 text-xs text-gray-600">Avance actual del proyecto: <span class="font-semibold">{{ $overallPercent }}%</span></p>
                </div>
                <form method="POST" action="{{ route('projects.attachments.runs.store', $project) }}">
                    @csrf
                    <button
                        type="submit"
                        @disabled(!$canGenerateAttachmentPackage)
                        class="px-3 py-2 rounded-md text-xs font-semibold border transition-colors"
                        style="{{ $canGenerateAttachmentPackage ? 'background:#f59e0b;color:#ffffff;border-color:#d97706;' : 'background:#e5e7eb;color:#6b7280;border-color:#d1d5db;cursor:not-allowed;' }}">
                        Generar paquete con adjuntos
                    </button>
                </form>
            </div>

            @if (!$canGenerateAttachmentPackage)
                <p class="mt-2 text-xs text-amber-700">Aun no se habilita: el proyecto debe estar al 100%.</p>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Health check</h4>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-700">
                <span class="rounded-full px-2 py-0.5 {{ $attachmentPdfHealth['python_ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    Python {{ $attachmentPdfHealth['python_ok'] ? 'OK' : 'ERROR' }}
                </span>
                <span class="rounded-full px-2 py-0.5 {{ $attachmentPdfHealth['script_exists'] ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    Script {{ $attachmentPdfHealth['script_exists'] ? 'OK' : 'NO ENCONTRADO' }}
                </span>
            </div>
            <div class="mt-2 text-[11px] text-gray-500">Binario: {{ $attachmentPdfHealth['python_bin'] }}</div>
            <div class="text-[11px] text-gray-500 break-all">Script: {{ $attachmentPdfHealth['script_path'] }}</div>
            @if (!empty($attachmentPdfHealth['python_version']))
                <div class="text-[11px] text-gray-500">Version: {{ $attachmentPdfHealth['python_version'] }}</div>
            @endif
            @if (!empty($attachmentPdfHealth['python_error']))
                <div class="mt-1 text-[11px] text-rose-600 break-all">Error: {{ $attachmentPdfHealth['python_error'] }}</div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Historial de ejecuciones</h4>

            @if ($attachmentRuns->isEmpty())
                <p class="mt-2 text-sm text-gray-500">Aun no hay ejecuciones.</p>
            @else
                <div class="mt-3 space-y-2">
                    @foreach ($attachmentRuns as $run)
                        <div class="flex flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-3 md:flex-row md:items-center md:justify-between">
                            <div class="text-xs text-gray-700">
                                <span class="font-semibold">Run #{{ $run->id }}</span>
                                <span class="ml-2">Estado: {{ $run->status }}</span>
                                @if (in_array($run->status, ['pending', 'running'], true))
                                    <span class="ml-2 text-indigo-700">
                                        Etapa: {{ data_get($run->meta, 'stage_label', 'Procesando') }}
                                        @if (data_get($run->meta, 'stage_percent') !== null)
                                            @if (data_get($run->meta, 'stage_detail_percent') !== null)
                                                ({{ (int) data_get($run->meta, 'stage_detail_percent') }}% etapa | {{ (int) data_get($run->meta, 'stage_percent') }}% total)
                                            @else
                                                ({{ (int) data_get($run->meta, 'stage_percent') }}% total)
                                            @endif
                                        @endif
                                    </span>
                                @endif
                                @if ($run->version_number)
                                    <span class="ml-2">Version: V{{ $run->version_number }}</span>
                                @endif
                                @if ($run->generated_pdf_count)
                                    <span class="ml-2">PDFs: {{ $run->generated_pdf_count }}</span>
                                @endif
                                @if (data_get($run->meta, 'heartbeat_at'))
                                    <span class="ml-2 text-gray-500">Actualizado: {{ data_get($run->meta, 'heartbeat_at') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($run->zip_local_path && file_exists($run->zip_local_path))
                                    <a href="{{ route('projects.attachments.runs.download', [$project, $run]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                        {{ $run->status === 'success' ? 'Descargar ZIP' : 'Descargar ZIP (local)' }}
                                    </a>
                                @endif
                                @if ($run->error_message)
                                    <span class="text-xs text-rose-600">{{ $run->error_message }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($hasActiveRuns)
        <script>
            setTimeout(function() {
                window.location.reload();
            }, 8000);
        </script>
    @endif
</x-filament-panels::page>
