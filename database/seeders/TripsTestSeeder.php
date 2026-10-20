<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use STS\Models\Trip;
use STS\Models\TripPoint;

class TripsTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $t1 = Trip::factory()->create();
        $t1->points()->save(TripPoint::factory()->rosario()->make());
        $t1->points()->save(TripPoint::factory()->mendoza()->make());
    }
}
