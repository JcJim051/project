<x-filament-panels::page>
    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-5">
            <h2 class="text-base font-semibold text-gray-900">Reglas de aprobación interna</h2>
            <p class="mt-1 text-sm text-gray-600">
                Controla cuándo la generación de carteras requiere la doble llave de Dirección y Planeación AIM.
            </p>
        </div>

        <form wire:submit="save" class="space-y-5">
            {{ $this->form }}

            <div class="flex justify-end">
                <button type="submit" class="inline-flex h-9 items-center rounded-md px-4 text-sm font-semibold" style="background:#16a34a;color:#ffffff;border:1px solid #15803d;">
                    Guardar configuración
                </button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
