<?php

namespace Tests\Unit\Services\Logic;

use Mockery;
use STS\Models\Car;
use STS\Models\CarBrand;
use STS\Models\CarColor;
use STS\Models\CarModel;
use STS\Models\User;
use STS\Repository\CarsRepository;
use STS\Services\Logic\CarsManager;
use Tests\TestCase;

class CarsManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function manager(): CarsManager
    {
        return new CarsManager(new CarsRepository);
    }

    private function validCarData(string $patenteSuffix = ''): array
    {
        $suffix = $patenteSuffix ?: substr(uniqid('', true), 0, 4);
        $brand = CarBrand::factory()->create();
        $model = CarModel::factory()->create(['car_brand_id' => $brand->id]);
        $color = CarColor::factory()->create();

        return [
            'patente' => 'AA'.$suffix,
            'description' => 'Family car',
            'car_brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'car_color_id' => $color->id,
            'year' => (int) date('Y') - 1,
        ];
    }

    public function test_validator_requires_patente_and_catalog_on_create(): void
    {
        $v = $this->manager()->validator([], 1, null, true);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('patente'));
        $this->assertTrue($v->errors()->has('car_color_id'));
        $this->assertTrue($v->errors()->has('car_brand_id'));
        $this->assertTrue($v->errors()->has('year'));
    }

    public function test_validator_rejects_year_outside_allowed_range(): void
    {
        $v = $this->manager()->validator(array_merge($this->validCarData(), [
            'year' => 1899,
        ]), 1, null, true);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('year'));

        $v = $this->manager()->validator(array_merge($this->validCarData(), [
            'year' => (int) date('Y') + 1,
        ]), 1, null, true);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('year'));
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

        $v = $this->manager()->validator(array_merge($this->validCarData(), [
            'patente' => $patente,
            'description' => 'Second',
        ]), $user->id, null, true);

        $this->assertTrue($v->fails());
        $this->assertTrue($v->errors()->has('patente'));
    }

    public function test_create_allows_multiple_cars_for_same_user(): void
    {
        $user = User::factory()->create();
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'EX'.substr(uniqid('', true), 0, 6),
            'description' => 'Existing',
        ]);

        $manager = $this->manager();
        $data = $this->validCarData('SECOND');
        $car = $manager->create($user, $data);

        $this->assertInstanceOf(Car::class, $car);
        $this->assertDatabaseHas('cars', [
            'user_id' => $user->id,
            'patente' => $data['patente'],
        ]);
        $this->assertSame(2, Car::query()->where('user_id', $user->id)->count());
    }

    public function test_create_rejects_duplicate_active_patente_for_same_user(): void
    {
        $user = User::factory()->create();
        $patente = 'DU'.substr(uniqid('', true), 0, 6);
        Car::factory()->create([
            'user_id' => $user->id,
            'patente' => $patente,
            'description' => 'First',
        ]);

        $manager = $this->manager();
        $result = $manager->create($user, array_merge($this->validCarData(), [
            'patente' => $patente,
            'description' => 'Duplicate attempt',
        ]));

        $this->assertNull($result);
        $errors = $manager->getErrors();
        $this->assertTrue(is_object($errors) ? $errors->has('patente') : isset($errors['patente']));
    }

    public function test_create_allows_patente_reused_after_previous_car_was_soft_deleted(): void
    {
        $user = User::factory()->create();
        $patente = 'RE'.substr(uniqid('', true), 0, 6);
        $old = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => $patente,
            'description' => 'Old car',
        ]);
        $old->delete();

        $manager = $this->manager();
        $car = $manager->create($user, array_merge($this->validCarData(), [
            'patente' => $patente,
            'description' => 'Replacement car',
        ]));

        $this->assertInstanceOf(Car::class, $car);
        $this->assertSame($patente, $car->patente);
    }

    public function test_create_restores_soft_deleted_car_with_same_patente_instead_of_creating_new_row(): void
    {
        $user = User::factory()->create();
        $patente = 'RS'.substr(uniqid('', true), 0, 6);
        $old = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => $patente,
            'description' => 'Old car',
        ]);
        $oldId = $old->id;
        $old->delete();

        $manager = $this->manager();
        $car = $manager->create($user, array_merge($this->validCarData(), [
            'patente' => $patente,
            'description' => 'Restored car',
        ]));

        $this->assertInstanceOf(Car::class, $car);
        $this->assertSame($oldId, $car->id);
        $this->assertSame('Restored car', $car->fresh()->description);
        $this->assertNull($car->fresh()->deleted_at);
        $this->assertSame(
            1,
            Car::withTrashed()->where('user_id', $user->id)->where('patente', $patente)->count()
        );
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

        $result = $manager->create($user, array_merge($this->validCarData(), [
            'patente' => 'TOOLONGPATX',
            'description' => 'x',
        ]));

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
        $updated = $manager->update($user, $car->id, array_merge($this->validCarData(), [
            'patente' => 'ZZ'.substr(uniqid('', true), 0, 6),
            'description' => 'After update',
        ]));

        $this->assertNotNull($updated);
        $this->assertSame('After update', $updated->fresh()->description);
    }

    public function test_validator_update_allows_same_patente_for_current_car_id(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'AA123BB',
            'description' => 'Current car',
        ]);

        $v = $this->manager()->validator([
            'patente' => 'AA123BB',
            'description' => 'Updated description',
            'brand_other' => 'Custom',
            'model_other' => 'Model',
        ], $user->id, $car->id);

        $this->assertFalse($v->fails());
    }

    public function test_update_allows_patente_used_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $target = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'TRG123',
            'description' => 'Target',
        ]);
        Car::factory()->create([
            'user_id' => $otherUser->id,
            'patente' => 'DUP123',
            'description' => 'Other user car',
        ]);

        $manager = $this->manager();
        $result = $manager->update($user, $target->id, array_merge($this->validCarData(), [
            'patente' => 'DUP123',
            'description' => 'Should pass',
        ]));

        $this->assertNotNull($result);
        $this->assertSame('DUP123', $result->fresh()->patente);
    }

    public function test_update_returns_null_when_car_not_found_or_not_owned(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->update($user, 999_999_999, $this->validCarData()));
        $this->assertSame('car_not_found', $manager->getErrors()['error']);
    }

    public function test_update_returns_validation_errors_for_invalid_payload(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'VAL123',
            'description' => 'Before',
        ]);
        $manager = $this->manager();

        $result = $manager->update($user, $car->id, [
            'patente' => 'TOOLONGPATX',
            'description' => '',
            'brand_other' => 'Other brand',
            'model_other' => 'Other model',
        ]);

        $this->assertNull($result);
        $this->assertTrue($manager->getErrors()->has('patente'));
    }

    public function test_show_accepts_equivalent_scalar_ids_for_owner_check(): void
    {
        $persisted = User::factory()->create();
        $viewer = new User;
        $viewer->id = (string) $persisted->id;
        $car = Car::factory()->create([
            'user_id' => $persisted->id,
            'patente' => 'EQ1234',
            'description' => 'Equivalent owner id',
        ]);

        $found = $this->manager()->show($viewer, $car->id);
        $this->assertNotNull($found);
        $this->assertSame($car->id, $found->id);
    }

    public function test_delete_soft_deletes_car_for_owner(): void
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
        $this->assertNotNull(Car::withTrashed()->find($id)?->deleted_at);
    }

    public function test_delete_returns_null_when_not_found(): void
    {
        $user = User::factory()->create();
        $manager = $this->manager();

        $this->assertNull($manager->delete($user, 999_999_999));
        $this->assertSame('car_not_found', $manager->getErrors()['error']);
    }

    public function test_delete_returns_null_with_can_delete_car_when_repository_delete_fails(): void
    {
        $user = User::factory()->create();
        $car = Car::factory()->create([
            'user_id' => $user->id,
            'patente' => 'FAIL01',
            'description' => 'Cannot delete',
        ]);

        $repo = Mockery::mock(CarsRepository::class);
        $repo->shouldReceive('show')->once()->with($car->id)->andReturn($car);
        $repo->shouldReceive('delete')->once()->with(Mockery::on(fn ($m) => $m->id === $car->id))->andReturn(false);

        $manager = new CarsManager($repo);
        $this->assertNull($manager->delete($user, $car->id));
        $this->assertSame('can_delete_car', $manager->getErrors()['error']);
    }

    public function test_create_with_other_brand_and_model(): void
    {
        $user = User::factory()->create();
        $color = CarColor::factory()->create();
        $manager = $this->manager();

        $car = $manager->create($user, [
            'patente' => 'OT1234',
            'car_color_id' => $color->id,
            'year' => (int) date('Y') - 5,
            'brand_other' => 'Custom Make',
            'model_other' => 'Custom Model',
        ]);

        $this->assertInstanceOf(Car::class, $car);
        $this->assertSame('Custom Make', $car->brand_other);
        $this->assertSame('Custom Model', $car->model_other);
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
