<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use STS\Models\CarColor;

class CarColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Blanco', 'hex' => '#FFFFFF', 'sort_order' => 1],
            ['name' => 'Negro', 'hex' => '#000000', 'sort_order' => 2],
            ['name' => 'Gris', 'hex' => '#808080', 'sort_order' => 3],
            ['name' => 'Plata', 'hex' => '#C0C0C0', 'sort_order' => 4],
            ['name' => 'Rojo', 'hex' => '#FF0000', 'sort_order' => 5],
            ['name' => 'Azul', 'hex' => '#0000FF', 'sort_order' => 6],
            ['name' => 'Verde', 'hex' => '#008000', 'sort_order' => 7],
            ['name' => 'Amarillo', 'hex' => '#FFFF00', 'sort_order' => 8],
            ['name' => 'Otro', 'hex' => null, 'sort_order' => 99],
        ];

        foreach ($colors as $color) {
            CarColor::query()->updateOrCreate(
                ['slug' => Str::slug($color['name'])],
                [
                    'name' => $color['name'],
                    'hex' => $color['hex'],
                    'sort_order' => $color['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
