<?php

use Illuminate\Database\Seeder;
use STS\Entities\TripPoint;
use STS\Entities\Trip;

class TripsTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
         $t1 = factory(Trip::class)->create(); 
         $t1->points()->save(factory(TripPoint::class, 'rosario')->make());
         $t1->points()->save(factory(TripPoint::class, 'mendoza')->make());
    }
}