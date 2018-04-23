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
        'name'           => $faker->name,
        'email'          => $faker->safeEmail,
        'password'       => bcrypt('123456'),
        'remember_token' => str_random(10),
        'active'         => true,
        'emails_notifications' => true,
    ];
});

$factory->define(STS\Entities\TripPoint::class, function (Faker\Generator $faker) {
    return [
        'address'      => $faker->streetAddress,
        'json_address' => ['city' => $faker->city],
        'lat'          => $faker->latitude,
        'lng'          => $faker->longitude,
    ];
});

$factory->define(STS\Entities\Trip::class, function ($faker) {
    return [
        'is_passenger'          => 0,
        'from_town'             => $faker->streetAddress,
        'to_town'               => $faker->streetAddress,
        'trip_date'             => Carbon\Carbon::now()->addHour(),
        'total_seats'           => 5,
        'friendship_type_id'    => 2,
        'estimated_time'        => '05:00',
        'distance'              => 365,
        'co2'                   => 50,
        'description'           => 'hola mundo',
        'mail_send'             => false,
        'user_id'               => function () {
            return factory(STS\User::class)->create()->id;
        },
    ];
});

$factory->defineAs(STS\Entities\TripPoint::class, 'rosario', function ($faker) {
    return [
        'address'      => 'Rosario, Santa Fe, ARgentina',
        'json_address' => ['ciudad' => 'Rosario', 'provincia' => 'Santa Fe'],
        'lat'          => -32.946525,
        'lng'          => -60.669847,
        'sin_lat'          => sin(deg2rad(-32.946525)),
        'sin_lng'          => sin(deg2rad(-60.669847)),
        'cos_lat'          => cos(deg2rad(-32.946525)),
        'cos_lng'          => cos(deg2rad(-60.669847)),
    ];
});

$factory->defineAs(STS\Entities\TripPoint::class, 'buenos_Aires', function ($faker) {
    return [
        'address'      => 'Buenos Aires, Argentina',
        'json_address' => ['ciudad' => 'Buenos Aires', 'provincia' => 'Buenos Aires'],
        'lat'          => -34.608903,
        'lng'          => -58.404521,
        'sin_lat'          => sin(deg2rad(-34.608903)),
        'sin_lng'          => sin(deg2rad( -58.404521)),
        'cos_lat'          => cos(deg2rad(-34.608903)),
        'cos_lng'          => cos(deg2rad( -58.404521)),
    ];
});

$factory->defineAs(STS\Entities\TripPoint::class, 'cordoba', function ($faker) {
    return [
        'address'      => 'Cordoba, Cordoba, Argentina',
        'json_address' => ['ciudad' => 'Cordoba', 'provincia' => 'Cordoba'],
        'lat'          => -31.421045,
        'lng'          => -64.190543,
        'sin_lat'          => sin(deg2rad(-31.421045)),
        'sin_lng'          => sin(deg2rad(-64.190543)),
        'cos_lat'          => cos(deg2rad(-31.421045)),
        'cos_lng'          => cos(deg2rad(-64.190543)),
    ];
});

$factory->defineAs(STS\Entities\TripPoint::class, 'mendoza', function ($faker) {
    return [
        'address'      => 'Mendoza, Mendoza, Argentina',
        'json_address' => ['ciudad' => 'Mendoza', 'provincia' => 'Mendoza'],
        'lat'          => -32.897273,
        'lng'          => -68.834067,
        'sin_lat'          => sin(deg2rad( -32.897273)),
        'sin_lng'          => sin(deg2rad(-68.834067)),
        'cos_lat'          => cos(deg2rad( -32.897273)),
        'cos_lng'          => cos(deg2rad(-68.834067)),
    ];
});

$factory->define(STS\Entities\Subscription::class, function ($faker) {
    return [ 
        'state'                 => true,
        'trip_date'             => Carbon\Carbon::now(),
        'user_id'               => function () {
            return factory(STS\User::class)->create()->id;
        },
    ];
});

$factory->define(STS\Entities\Car::class, function ($faker) {
    return [
        'patente'     => 'ASD 123',
        'description' => 'sandero',
    ];
});

$factory->define(STS\Entities\Conversation::class, function (Faker\Generator $faker) {
    return [
        'trip_id' => null,
        'title' => $faker->safeEmail,
        'type' => STS\Entities\Conversation::TYPE_PRIVATE_CONVERSATION,
    ];
});

$factory->define(STS\Entities\Passenger::class, function (Faker\Generator $faker) {
    return [
        'request_state' => STS\Entities\Passenger::STATE_PENDING,
        'passenger_type' => STS\Entities\Passenger::TYPE_PASAJERO,
    ];
});

$factory->defineAs(STS\Entities\Passenger::class, 'aceptado', function ($faker) {
    return [
        'request_state' => STS\Entities\Passenger::STATE_ACCEPTED,
        'passenger_type' => STS\Entities\Passenger::TYPE_PASAJERO,
    ];
});
