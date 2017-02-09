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
    ];
});

$factory->define(STS\Entities\Trip::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'user_id' => 1,
        'from_town' => $faker->city,
        'to_town' => $faker->city,
        "trip_date" => $faker->dateTime,
        'description' => $faker->realText,
        'total_seats' => $faker->randomDigitNotNull,
        "friendship_type_id" => $faker->randomDigitNotNull,
        "distance" => $faker->randomNumber,
        'estimated_time' => "sth hours",
        "co2" => $faker->randomNumber,
        "es_recurrente" => 0,
        "trip_type" => 0,
        "mail_send" => false,
        'tripscol' => 'asd'
    ];
});