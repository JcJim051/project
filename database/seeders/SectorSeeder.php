<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            'Agricultura',
            'Ambiente',
            'Ciencia',
            'Comercio',
            'Cultura',
            'Deporte',
            'Educación',
            'Social',
            'Justicia',
            'Minas',
            'Salud',
            'Tecnologías',
            'Transporte',
            'Vivienda',
        ];

        foreach ($sectors as $name) {
            Sector::firstOrCreate(['nombre' => $name]);
        }
    }
}
