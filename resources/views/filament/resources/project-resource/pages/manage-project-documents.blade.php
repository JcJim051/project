<x-filament-panels::page>
    <div class="space-y-4">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-800">Resumen del proyecto</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-4">
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Objeto</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->objeto_proyecto ?: '-' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">ID proyecto</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->id_proyecto ?: 'Sin ID proyecto' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">BPIN</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->bipin ?: 'Sin BPIN' }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</div>
                    <div class="mt-1 text-sm font-semibold text-gray-800">{{ now()->format('d/m/Y') }}</div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-800">Certificaciones disponibles</h3>
                <p class="mt-1 text-xs text-gray-500">Solo se muestran las certificaciones marcadas en requisitos.</p>
            </div>

            @if ($templates->isEmpty())
                <p class="mt-4 text-sm text-gray-500">No hay certificaciones disponibles para este proyecto.</p>
            @else
                <form method="POST" action="{{ route('projects.documents.zip', $project) }}" class="mt-4 space-y-4">
                    @csrf

                    <div class="space-y-2">
                        @foreach ($templates as $template)
                            <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <label class="flex items-center gap-3 text-sm font-medium text-gray-700">
                                    <input type="checkbox" name="templates[]" value="{{ $template->id }}" class="rounded border-gray-300 text-lime-600 focus:ring-lime-500">
                                    {{ $template->nombre }}
                                </label>
                                <a
                                    href="{{ route('projects.documents.download', [$project, $template]) }}"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Descargar
                                </a>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-md border border-lime-200 bg-lime-50 px-3 py-1.5 text-xs font-semibold text-lime-700 hover:bg-lime-100"
                        >
                            Descargar seleccionados (ZIP)
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-filament-panels::page>
