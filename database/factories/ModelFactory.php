<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(STS\User::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->safeEmail,
        'password' => bcrypt("123456"),
        'remember_token' => str_random(10),
        'active' => true
    ];
});

$factory->define(STS\Entities\TripPoint::class, function (Faker\Generator $faker) {
    return [
        'address' => $faker->streetAddress,
        'json_address' => ['city' => $faker->city],
        'lat' => $faker->latitude,
        'lng' => $faker->longitude
    ];
});

$factory->define(STS\Entities\Trip::class, function ($faker) {
    return [
        'is_passenger'          => 0,
        'from_town'             => $faker->streetAddress,
        'to_town'               => $faker->streetAddress,
        'trip_date'             => Carbon\Carbon::now(),
        'total_seats'           => 5,
        'friendship_type_id'    => 2,
        'estimated_time'        => '05:00',
        'distance'              => 365,
        'co2'                   => 50,
        'description'           => 'hola mundo', 
        'user_id' => function () {
            return factory(STS\User::class)->create()->id;
        }
    ];
});