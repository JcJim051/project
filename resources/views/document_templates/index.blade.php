<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Plantillas de certificación') }}
            </h2>
            <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Volver</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Subir plantillas</h3>
                    <p class="text-sm text-gray-500">
                        Usa marcadores en el Word: @verbatim{{OBJETO}}@endverbatim, @verbatim{{BPIN}}@endverbatim, @verbatim{{FORMULADOR}}@endverbatim, @verbatim{{FECHA}}@endverbatim.
                    </p>
                </div>
                <form method="POST" action="{{ route('document_templates.store') }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Plantillas (.docx)</label>
                        <input type="file" name="plantillas[]" multiple class="mt-1 w-full text-sm text-gray-700" required>
                    </div>
                    <button class="h-10 px-4 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700" type="submit">
                        Cargar plantillas
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Plantillas disponibles</h3>
                @if ($templates->isEmpty())
                    <p class="text-sm text-gray-500">No hay plantillas cargadas.</p>
                @else
                    <div class="grid gap-3">
                        @foreach ($templates as $template)
                            <div class="flex items-center justify-between rounded-md border border-gray-100 p-3">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">{{ $template->nombre }}</div>
                                    <div class="text-xs text-gray-500">Actualizado: {{ $template->updated_at->format('d/m/Y H:i') }}</div>
                                </div>
                                <form method="POST" action="{{ route('document_templates.destroy', $template) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
