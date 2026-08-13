<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class AdminTripExcessContributionControllerIntegrationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_index_returns_trips_with_higher_description_contribution(): void
    {
        $admin = $this->admin();
        $driverWithNote = User::factory()->create([
            'name' => 'Driver With Note',
            'private_note' => 'Revisar historial',
        ]);
        $driverWithoutNote = User::factory()->create([
            'name' => 'Driver Without Note',
            'private_note' => null,
        ]);

        $excessTrip = Trip::factory()->create([
            'user_id' => $driverWithNote->id,
            'from_town' => 'Buenos Aires',
            'to_town' => 'Rosario',
            'seat_price_cents' => 1500000,
            'description' => 'La contribución es de $24000 por persona',
        ]);

        Trip::factory()->create([
            'user_id' => $driverWithoutNote->id,
            'seat_price_cents' => 1500000,
            'description' => 'Contribución $15000',
        ]);

        Trip::factory()->create([
            'user_id' => $driverWithoutNote->id,
            'seat_price_cents' => 1500000,
            'description' => 'Sin montos en la descripción',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/trip-excess-contributions')->assertOk();
        $this->assertArrayHasKey('data', $response->json());
        $this->assertArrayHasKey('meta', $response->json());

        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertSame([
            'id',
            'from_town',
            'to_town',
            'seat_price_cents',
            'potential_seat_price_cents',
            'has_private_note',
            'user_id',
        ], array_keys($rows[0]));

        $row = $rows[0];
        $this->assertSame($excessTrip->id, $row['id']);
        $this->assertSame('Buenos Aires', $row['from_town']);
        $this->assertSame('Rosario', $row['to_town']);
        $this->assertSame(1500000, $row['seat_price_cents']);
        $this->assertSame(2400000, $row['potential_seat_price_cents']);
        $this->assertTrue($row['has_private_note']);
        $this->assertSame($driverWithNote->id, $row['user_id']);
    }

    public function test_index_supports_pagination_meta(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create();

        foreach (range(1, 3) as $index) {
            Trip::factory()->create([
                'user_id' => $driver->id,
                'seat_price_cents' => 100000,
                'description' => 'Pago $'.(2000 + $index).' por persona',
            ]);
        }

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/trip-excess-contributions?per_page=2&page=1')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame([
            'current_page' => 1,
            'per_page' => 2,
            'total' => 3,
            'total_pages' => 2,
        ], $response->json('meta.pagination'));
    }
}
