<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use STS\Models\CarBrand;
use STS\Models\CarModel;

class CarCatalogSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{brands: list<array{name: string, slug: string, argautos_id: int, is_active: bool}>, models: list<array{brand_argautos_id: int, name: string, slug: string, argautos_id: int, is_active: bool}>} $catalog */
        $catalog = require database_path('seeders/data/car_catalog.php');

        foreach ($catalog['brands'] as $brandData) {
            CarBrand::query()->updateOrCreate(
                ['argautos_id' => $brandData['argautos_id']],
                [
                    'name' => $brandData['name'],
                    'slug' => $brandData['slug'],
                    'is_active' => $brandData['is_active'],
                ]
            );
        }

        foreach ($catalog['models'] as $modelData) {
            $brand = CarBrand::query()
                ->where('argautos_id', $modelData['brand_argautos_id'])
                ->first();

            if (! $brand) {
                continue;
            }

            CarModel::query()->updateOrCreate(
                [
                    'car_brand_id' => $brand->id,
                    'argautos_id' => $modelData['argautos_id'],
                ],
                [
                    'name' => $modelData['name'],
                    'slug' => $modelData['slug'],
                    'is_active' => $modelData['is_active'],
                ]
            );
        }
    }
}
