<div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Ficha previa de revisión interna (MGA)</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Proyecto: <span class="font-semibold">{{ $project->nombre_clave ?: $project->nombre }}</span>
                        · Estado: <span class="font-semibold uppercase">{{ $transferRequest->status }}</span>
                    </p>
                </div>
                <a href="{{ route('filament.admin.resources.project-transfer-requests.index') }}" class="inline-flex h-9 items-center rounded-md border border-gray-300 px-3 text-sm text-gray-700 hover:bg-gray-50">
                    Volver a autorizaciones
                </a>
            </div>

            @if (session('status'))
                <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mt-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif
        </div>

        <div class="grid gap-4 lg:grid-cols-12">
            <section class="lg:col-span-8 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Requisitos (solo lectura con comentarios)</h3>
                    <span class="text-xs text-gray-500">Puedes comentar por requisito y luego decidir.</span>
                </div>

                <form method="POST" action="{{ route('project-transfer-requests.comments', $transferRequest) }}" class="space-y-3">
                    @csrf
                    @foreach ($requirementsByGroup as $groupCode => $groupRequirements)
                        <details class="rounded-lg border border-gray-200" {{ $loop->first ? 'open' : '' }}>
                            <summary class="cursor-pointer select-none px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50">
                                {{ $groupLabels[$groupCode] ?? ('Grupo ' . $groupCode) }}
                            </summary>
                            <div class="space-y-2 p-3">
                                @foreach ($groupRequirements as $req)
                                    @php
                                        $title = trim((string) ($req->nombre_documento ?: $req->requisito));
                                        $evCount = ($evidenceByRequirement[$req->id] ?? collect())->count();
                                        $comment = $comments[$req->id]->comment ?? '';
                                    @endphp
                                    <div class="rounded-md border border-gray-200 p-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">{{ $title }}</div>
                                                <div class="text-xs text-gray-500">Carpeta: {{ $req->carpeta ?: 'Sin carpeta' }}</div>
                                            </div>
                                            <span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] {{ $evCount > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ $evCount > 0 ? ($evCount . ' evidencia(s)') : 'Sin evidencia' }}
                                            </span>
                                        </div>
                                        <textarea
                                            name="comments[{{ $req->id }}]"
                                            rows="2"
                                            class="mt-2 w-full rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700"
                                            placeholder="Comentario para este requisito (hallazgo, ajuste, observación)">{{ $comment }}</textarea>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach

                    @if ($transferRequest->status === 'pending')
                        <div class="pt-2">
                            <button type="submit" class="inline-flex h-9 items-center rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Guardar comentarios por requisito
                            </button>
                        </div>
                    @endif
                </form>
            </section>

            <aside class="lg:col-span-4 space-y-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-gray-900">Decisión</h3>
                    <p class="mt-1 text-xs text-gray-500">Estos botones consolidan observaciones y generan el mensaje final para el equipo del proyecto.</p>

                    @if ($transferRequest->status === 'pending')
                        <form method="POST" action="{{ route('project-transfer-requests.decide', ['transferRequest' => $transferRequest, 'decision' => 'approve']) }}" class="mt-3 space-y-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                            @csrf
                            <label class="text-xs font-semibold text-emerald-700">Aprobar (comentario obligatorio)</label>
                            <textarea name="decision_note" required rows="3" class="w-full rounded-md border border-emerald-300 px-2 py-1 text-xs text-gray-700"></textarea>
                            <button type="submit" class="inline-flex h-9 items-center rounded-md border border-emerald-400 bg-emerald-600 px-3 text-sm font-semibold text-white hover:bg-emerald-700">Aprobar</button>
                        </form>

                        <form method="POST" action="{{ route('project-transfer-requests.decide', ['transferRequest' => $transferRequest, 'decision' => 'reject']) }}" class="mt-3 space-y-2 rounded-lg border border-rose-200 bg-rose-50 p-3">
                            @csrf
                            <label class="text-xs font-semibold text-rose-700">Rechazar (comentario obligatorio)</label>
                            <textarea name="decision_note" required rows="3" class="w-full rounded-md border border-rose-300 px-2 py-1 text-xs text-gray-700"></textarea>
                            <button type="submit" class="inline-flex h-9 items-center rounded-md border border-rose-400 bg-rose-600 px-3 text-sm font-semibold text-white hover:bg-rose-700">Rechazar</button>
                        </form>
                    @else
                        <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 whitespace-pre-line">{{ $transferRequest->decision_note }}</div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</div>
