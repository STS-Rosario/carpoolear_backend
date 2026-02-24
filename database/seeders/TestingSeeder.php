<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use STS\Models\User;

class TestingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $data = ['email' => 'user' . $i . '@g.com'];
            if ($i === 0) {
                $data['is_admin'] = true;
            }
            $users[] = User::factory()->create($data);
        }

        // for ($i = 0; $i < 10; $i++) {
        //     for ($j = 0; $j < 5; $j++) {
        //         $collection = collect(['rosario', 'buenos_Aires', 'mendoza', 'cordoba']);

        //         $p1 = $collection->random();
        //         $p2 = $collection->random();
        //         $date = $this->randomDate('now', '+1 month');

        //         $t1 = factory(Trip::class)->create(['user_id' => $users[$i]->id, 'trip_date' => $date]);
        //         $t1->points()->save(factory(TripPoint::class, $p1)->make());
        //         $t1->points()->save(factory(TripPoint::class, $p2)->make());
        //     }
        // }
    }

    public function randomDate($start_date, $end_date)
    {
        // Convert to timetamps
        $min = strtotime($start_date);
        $max = strtotime($end_date);

        // Generate random number using above bounds
        $val = rand($min, $max);

        // Convert back to desired date format
        return date('Y-m-d H:i:s', $val);
    }
}
