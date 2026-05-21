<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Generar certificaciones</h2>
                <p class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</p>
            </div>
            <a
                href="{{ route('projects.manage', $project) }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            >
                Volver a gestionar
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
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

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800">Resumen del proyecto</h3>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Objeto</div>
                        <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->objeto_proyecto }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">BIPIN</div>
                        <div class="mt-1 text-sm font-semibold text-gray-800">{{ $project->bipin ?: 'Sin BIPIN' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</div>
                        <div class="mt-1 text-sm font-semibold text-gray-800">{{ now()->format('d/m/Y') }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-gray-800">Certificaciones disponibles</h3>
                <p class="mt-1 text-sm text-gray-500">Solo se muestran las certificaciones marcadas en requisitos.</p>

                @if ($templates->isEmpty())
                    <p class="mt-4 text-sm text-gray-500">No hay certificaciones disponibles para este proyecto.</p>
                @else
                    <form method="POST" action="{{ route('projects.documents.zip', $project) }}" class="mt-4 space-y-4">
                        @csrf

                        <div class="space-y-2">
                            @foreach ($templates as $template)
                                <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
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
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-lime-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-lime-700"
                            >
                                Descargar seleccionados (ZIP)
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
