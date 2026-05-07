<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-600">
            Configura la app OAuth de Google Drive desde el panel. Al guardar, se limpian los tokens para forzar reconexion.
        </p>

        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div>
                <x-filament::button type="submit">
                    Guardar credenciales
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>

