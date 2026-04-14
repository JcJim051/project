<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Generar certificaciones') }}
                </h2>
                <p class="text-sm text-gray-500">Proyecto: {{ $project->nombre }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('projects.manage', $project) }}" class="text-gray-500 hover:text-gray-700">Volver a gestionar</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-emerald-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-rose-50 p-4 text-rose-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <div class="text-xs text-gray-500">Objeto</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $project->objeto_proyecto }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">BIPIN</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $project->bipin ?? 'Sin BIPIN' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Fecha</div>
                        <div class="text-sm font-semibold text-gray-800">{{ now()->format('d/m/Y') }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Certificaciones disponibles</h3>
                        <p class="text-sm text-gray-500">Solo se muestran las certificaciones marcadas en requisitos.</p>
                    </div>
                </div>

                @if ($templates->isEmpty())
                    <p class="text-sm text-gray-500">No hay certificaciones disponibles para este proyecto.</p>
                @else
                    <form method="POST" action="{{ route('projects.documents.zip', $project) }}" class="space-y-4">
                        @csrf
                        <div class="grid gap-3">
                            @foreach ($templates as $template)
                                <div class="flex items-center justify-between rounded-md border border-gray-100 p-3">
                                    <label class="flex items-center gap-3 text-sm text-gray-700">
                                        <input type="checkbox" name="templates[]" value="{{ $template->id }}" class="rounded border-gray-300">
                                        {{ $template->nombre }}
                                    </label>
                                    <a href="{{ route('projects.documents.download', [$project, $template]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                                        Descargar
                                    </a>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                                Descargar seleccionados (ZIP)
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
