<?php

namespace Tests\Unit\Services\Logic;

use STS\Models\Car;
use STS\Models\User;
use STS\Repository\CarsRepository;
use STS\Services\Logic\CarsManager;
use Tests\TestCase;

class CarsManagerTest extends TestCase
{
    private function manager(): CarsManager
    {
        return new CarsManager(new CarsRepository);
    }

    private function validCarData(string $patenteSuffix = ''): array
    {
        $suffix = $patenteSuffix ?: substr(uniqid('', true), 0, 4);

        return [
            'patente' => 'AA'.$suffix,
            'description' => 'Family car',
        ];
    }

    public function test_validator_requires_patente_and_description(): void
    {
        $v = $this->manager()->validator([], 1);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('patente'));
        $this->assertTrue($v->errors()->has('description'));
    }

    public function test_validator_enforces_unique_patente_per_user_on_create(): void
    {
        $user = User::factory()->create();
        $patente = 'UN'.substr(uniqid('', true), 0, 6);
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => $patente,
            'description' => 'First',
        ]);

        $v = $this->manager()->validator([
            'patente' => $patente,
            'description' => 'Second',
        ], $user->id);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('patente'));
    }

    public function test_create_rejected_when_user_already_has_car(): void
    {
        $user = User::factory()->create();
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'EX'.substr(uniqid('', true), 0, 6),
            'description' => 'Existing',
        ]);

        $manager = $this->manager();
        $result = $manager->create($user, $this->validCarData());

        $this->assertNull($result);
        $errors = $manager->getErrors();
        $this->assertIsArray($errors);
        $this->assertSame('user_already_has_car', $errors['error']);
    }

    public function test_create_persists_car_when_valid(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();
        $data = $this->validCarData('NEW1');

        $car = $manager->create($user, $data);

        $this->assertInstanceOf(Car::class, $car);
        $this->assertNotNull($car->id);
        $this->assertDatabaseHas('cars', [
            'user_id' => $user->id,
            'patente' => $data['patente'],
        ]);
    }

    public function test_create_sets_validation_errors_for_invalid_payload(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $result = $manager->create($user, [
            'patente' => 'TOOLONGPATX',
            'description' => 'x',
        ]);

        $this->assertNull($result);
        $this->assertNotNull($manager->getErrors());
    }

    public function test_show_returns_car_only_for_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $owner->id,
            'patente' => 'OW'.substr(uniqid('', true), 0, 6),
            'description' => 'Owner car',
        ]);

        $this->assertTrue($this->manager()->show($owner, $car->id)->is($car));

        $otherManager = $this->manager();
        $this->assertNull($otherManager->show($other, $car->id));
        $this->assertSame('car_not_found', $otherManager->getErrors()['error']);
    }

    public function test_update_modifies_car_for_owner(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'UP'.substr(uniqid('', true), 0, 6),
            'description' => 'Before',
        ]);

        $manager = $this->manager();
        $updated = $manager->update($user, $car->id, [
            'patente' => 'ZZ'.substr(uniqid('', true), 0, 6),
            'description' => 'After update',
        ]);

        $this->assertNotNull($updated);
        $this->assertSame('After update', $updated->fresh()->description);
    }

    public function test_update_returns_null_when_car_not_found_or_not_owned(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->update($user, 999_999_999, $this->validCarData()));
        $this->assertSame('car_not_found', $manager->getErrors()['error']);
    }

    public function test_delete_removes_car_for_owner(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'DL'.substr(uniqid('', true), 0, 6),
            'description' => 'To delete',
        ]);
        $id = $car->id;

        $manager = $this->manager();
        $this->assertTrue($manager->delete($user, $id));
        $this->assertNull(Car::query()->find($id));
    }

    public function test_delete_returns_null_when_not_found(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->delete($user, 999_999_999));
        $this->assertSame('car_not_found', $manager->getErrors()['error']);
    }

    public function test_index_delegates_to_repository(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'IX'.substr(uniqid('', true), 0, 6),
            'description' => 'Indexed',
        ]);

        $cars = $this->manager()->index($user->fresh());

        $this->assertCount(1, $cars);
        $this->assertTrue($cars->first()->is($car));
    }
}
