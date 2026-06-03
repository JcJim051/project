<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Paquete PDF con adjuntos</h3>
                    <p class="text-xs text-gray-500">
                        Disponible desde {{ $attachmentsMinPercent }}%. Genera ZIP versionado (V{N}) y lo sube a Drive en 02 Cargue.
                    </p>
                    <p class="mt-1 text-xs text-gray-600">Avance actual del proyecto: <span class="font-semibold">{{ $overallPercent }}%</span></p>
                </div>
                <button
                    form="attachment-package-form"
                    type="submit"
                    @disabled(!$canGenerateAttachmentPackage || $hasActiveRuns)
                    class="px-3 py-2 rounded-md text-xs font-semibold border transition-colors"
                    style="{{ ($canGenerateAttachmentPackage && !$hasActiveRuns) ? 'background:#f59e0b;color:#ffffff;border-color:#d97706;' : 'background:#e5e7eb;color:#6b7280;border-color:#d1d5db;cursor:not-allowed;' }}">
                    Generar selección
                </button>
            </div>

            @if (!$canGenerateAttachmentPackage)
                <p class="mt-2 text-xs text-amber-700">Aun no se habilita: el proyecto debe estar al {{ $attachmentsMinPercent }}%.</p>
            @endif
        </div>

        <form id="attachment-package-form" method="POST" action="{{ route('projects.attachments.runs.store', $project) }}" class="rounded-lg border border-gray-200 bg-white p-4">
            @csrf
            <div class="flex flex-col gap-1 md:flex-row md:items-end md:justify-between">
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-600">Carteras a generar</h4>
                    <p class="mt-1 text-xs text-gray-500">Selecciona una o varias carteras. Si eliges una sola, se generará PDF directo; si eliges varias, se generará ZIP.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        data-attachment-select-all
                        class="text-[11px] font-medium text-emerald-700 underline-offset-2 transition hover:text-emerald-800 hover:underline">
                        Marcar todo
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        data-attachment-unselect-all
                        class="text-[11px] font-medium text-gray-500 underline-offset-2 transition hover:text-gray-700 hover:underline">
                        Desmarcar todo
                    </button>
                    <div class="w-full text-[11px] text-gray-500 md:w-auto md:pl-2">La selección se recuerda por proyecto.</div>
                </div>
            </div>

            @error('attachments_package')
                <div class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{{ $message }}</div>
            @enderror

            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($availableAttachmentDocuments as $document)
                    <label class="flex items-start gap-2 rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-xs text-gray-700 hover:border-amber-300 hover:bg-amber-50">
                        <input
                            type="checkbox"
                            name="selected_documents[]"
                            value="{{ $document['key'] }}"
                            @checked(in_array($document['key'], $selectedAttachmentDocuments, true))
                            class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="block font-semibold text-gray-800">{{ $document['title'] }}</span>
                            <span class="block text-[11px] text-gray-500">{{ $document['group'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </form>

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
                                @if ($run->output_type)
                                    <span class="ml-2">Salida: {{ strtoupper($run->output_type) }}</span>
                                @endif
                                @if ($run->generated_pdf_count)
                                    <span class="ml-2">PDFs: {{ $run->generated_pdf_count }}</span>
                                @endif
                                @if (data_get($run->meta, 'heartbeat_at'))
                                    <span class="ml-2 text-gray-500">Actualizado: {{ data_get($run->meta, 'heartbeat_at') }}</span>
                                @endif
                                @if (in_array($run->status, ['pending', 'running'], true))
                                    @php($runPercent = max(0, min(100, (int) data_get($run->meta, 'stage_percent', $run->status === 'running' ? 5 : 1))))
                                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 md:max-w-xl" data-run-progress-wrap="{{ $run->id }}">
                                        <div class="h-full rounded-full bg-amber-500 transition-all duration-500" style="width: {{ $runPercent }}%" data-run-progress-bar="{{ $run->id }}"></div>
                                    </div>
                                    <div class="mt-1 text-[11px] text-gray-500" data-run-progress-label="{{ $run->id }}">{{ $runPercent }}% total</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @php($downloadPath = $run->output_local_path ?: $run->zip_local_path)
                                @if ($downloadPath && file_exists($downloadPath))
                                    @if (($run->output_type ?: 'zip') === 'pdf')
                                        <a href="{{ route('projects.attachments.runs.preview', [$project, $run]) }}" target="_blank" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                            Ver PDF
                                        </a>
                                    @endif
                                    <a href="{{ route('projects.attachments.runs.download', [$project, $run]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                        Descargar {{ strtoupper($run->output_type ?: 'zip') }}
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

    @if ($activeAttachmentRun)
        @php($modalPercent = max(0, min(100, (int) data_get($activeAttachmentRun->meta, 'stage_percent', $activeAttachmentRun->status === 'running' ? 5 : 1))))
        <div data-attachment-progress-modal class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/40 px-4 py-6">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-gray-900/10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Generando paquete PDF</p>
                        <h3 class="mt-1 text-base font-semibold text-gray-900">Puedes seguir trabajando</h3>
                        <p class="mt-1 text-sm text-gray-500">El proceso continuará en segundo plano. Te llegará una notificación cuando termine.</p>
                    </div>
                    <button type="button" data-attachment-progress-close class="rounded-full p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                        <span class="sr-only">Cerrar</span>
                        ×
                    </button>
                </div>

                <div class="mt-5">
                    <div class="mb-2 flex items-center justify-between text-xs text-gray-600">
                        <span data-attachment-progress-stage>{{ data_get($activeAttachmentRun->meta, 'stage_label', 'Procesando') }}</span>
                        <span data-attachment-progress-percent>{{ $modalPercent }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-gray-200">
                        <div data-attachment-progress-bar class="h-full rounded-full bg-amber-500 transition-all duration-500" style="width: {{ $modalPercent }}%"></div>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500" data-attachment-progress-detail>
                        Run #{{ $activeAttachmentRun->id }} · Estado: {{ $activeAttachmentRun->status }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('click', function (event) {
            const form = document.getElementById('attachment-package-form');
            if (form && event.target.closest('[data-attachment-select-all]')) {
                form.querySelectorAll('input[name="selected_documents[]"]').forEach(function (checkbox) {
                    checkbox.checked = true;
                });
            }

            if (form && event.target.closest('[data-attachment-unselect-all]')) {
                form.querySelectorAll('input[name="selected_documents[]"]').forEach(function (checkbox) {
                    checkbox.checked = false;
                });
            }

            if (event.target.closest('[data-attachment-progress-close]')) {
                const modal = document.querySelector('[data-attachment-progress-modal]');
                if (modal) {
                    modal.remove();
                }
            }
        });

        @if ($activeAttachmentRunUrl)
            (function () {
                const statusUrl = @json($activeAttachmentRunUrl);
                const runId = @json($activeAttachmentRun->id);
                const modal = document.querySelector('[data-attachment-progress-modal]');
                const modalBar = document.querySelector('[data-attachment-progress-bar]');
                const modalStage = document.querySelector('[data-attachment-progress-stage]');
                const modalPercent = document.querySelector('[data-attachment-progress-percent]');
                const modalDetail = document.querySelector('[data-attachment-progress-detail]');
                const listBar = document.querySelector('[data-run-progress-bar="' + runId + '"]');
                const listLabel = document.querySelector('[data-run-progress-label="' + runId + '"]');

                function setProgress(percent, stage, detailPercent, status) {
                    percent = Math.max(0, Math.min(100, parseInt(percent || 0, 10)));
                    if (modalBar) modalBar.style.width = percent + '%';
                    if (modalStage) modalStage.textContent = stage || 'Procesando';
                    if (modalPercent) modalPercent.textContent = percent + '%';
                    if (modalDetail) {
                        modalDetail.textContent = 'Run #' + runId + ' · Estado: ' + status + (detailPercent !== null && detailPercent !== undefined ? ' · Etapa actual: ' + detailPercent + '%' : '');
                    }
                    if (listBar) listBar.style.width = percent + '%';
                    if (listLabel) listLabel.textContent = percent + '% total';
                }

                async function poll() {
                    try {
                        const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();
                        setProgress(data.stage_percent || (data.status === 'running' ? 5 : 1), data.stage_label, data.stage_detail_percent, data.status);
                        if (data.status === 'success' || data.status === 'failed') {
                            setTimeout(function () { window.location.reload(); }, 1500);
                            return;
                        }
                    } catch (error) {
                        // Keep the page usable even if one polling request fails.
                    }
                    setTimeout(poll, 3000);
                }

                poll();
            })();
        @endif
    </script>
</x-filament-panels::page>
