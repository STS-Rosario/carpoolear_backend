<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\SupportTicket;
use STS\Models\Trip;
use STS\Models\User;
use STS\Support\TripExcessContributionStatus;
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
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
        ]);

        SupportTicket::query()->create([
            'user_id' => $driverWithNote->id,
            'type' => 'excess_contribution',
            'subject' => 'Exceso de contribución',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
            'created_by' => $admin->id,
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
            'user_name',
            'exceso_contribucion_status',
            'excess_contribution_support_tickets_count',
        ], array_keys($rows[0]));

        $row = $rows[0];
        $this->assertSame($excessTrip->id, $row['id']);
        $this->assertSame('Buenos Aires', $row['from_town']);
        $this->assertSame('Rosario', $row['to_town']);
        $this->assertSame(1500000, $row['seat_price_cents']);
        $this->assertSame(2400000, $row['potential_seat_price_cents']);
        $this->assertTrue($row['has_private_note']);
        $this->assertSame($driverWithNote->id, $row['user_id']);
        $this->assertSame('Driver With Note', $row['user_name']);
        $this->assertSame(TripExcessContributionStatus::PENDIENTE, $row['exceso_contribucion_status']);
        $this->assertSame(1, $row['excess_contribution_support_tickets_count']);
    }

    public function test_show_returns_trip_and_creator_detail(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create([
            'name' => 'Creator Name',
            'email' => 'creator@example.com',
        ]);

        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'from_town' => 'Córdoba',
            'to_town' => 'Mendoza',
            'seat_price_cents' => 1500000,
            'description' => 'Pago $24000',
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::EN_PROCESO,
        ]);

        SupportTicket::query()->create([
            'user_id' => $driver->id,
            'type' => 'excess_contribution',
            'subject' => 'Ticket exceso',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/trip-excess-contributions/'.$trip->id)->assertOk();
        $data = $response->json('data');

        $this->assertSame($trip->id, $data['id']);
        $this->assertSame('Córdoba', $data['from_town']);
        $this->assertSame('Mendoza', $data['to_town']);
        $this->assertSame('Pago $24000', $data['description']);
        $this->assertSame(TripExcessContributionStatus::EN_PROCESO, $data['exceso_contribucion_status']);
        $this->assertSame($driver->id, $data['user_id']);
        $this->assertSame('Creator Name', $data['user_name']);
        $this->assertSame('creator@example.com', $data['user_email']);
        $this->assertSame(1, $data['excess_contribution_support_tickets_count']);
    }

    public function test_update_status_changes_exceso_contribucion_status(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $driver->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/trip-excess-contributions/'.$trip->id.'/status', [
            'status' => TripExcessContributionStatus::RESUELTO,
        ])->assertOk();

        $trip->refresh();
        $this->assertSame(TripExcessContributionStatus::RESUELTO, $trip->exceso_contribucion_status);
    }

    public function test_index_supports_pagination_meta(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create();

        foreach (range(1, 11) as $index) {
            Trip::factory()->create([
                'user_id' => $driver->id,
                'seat_price_cents' => 100000,
                'description' => 'Pago $'.(2000 + $index).' por persona',
                'has_potential_excess_contribution' => true,
                'description_potential_seat_price_cents' => (2000 + $index) * 100,
                'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
            ]);
        }

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/trip-excess-contributions?per_page=10&page=1')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame([
            'current_page' => 1,
            'per_page' => 10,
            'total' => 11,
            'total_pages' => 2,
        ], $response->json('meta.pagination'));
    }

    public function test_index_requires_action_only_hides_resuelto_and_descartado(): void
    {
        $admin = $this->admin();
        $driver = User::factory()->create();

        $pendingTrip = Trip::factory()->create([
            'user_id' => $driver->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
        ]);
        Trip::factory()->create([
            'user_id' => $driver->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::RESUELTO,
        ]);
        Trip::factory()->create([
            'user_id' => $driver->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::DESCARTADO,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson('api/admin/trip-excess-contributions?requires_action_only=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$pendingTrip->id], $ids);
    }

    public function test_index_sorts_by_user_name(): void
    {
        $admin = $this->admin();
        $driverA = User::factory()->create(['name' => 'Alpha Driver']);
        $driverB = User::factory()->create(['name' => 'Beta Driver']);

        Trip::factory()->create([
            'user_id' => $driverB->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
        ]);
        Trip::factory()->create([
            'user_id' => $driverA->id,
            'has_potential_excess_contribution' => true,
            'description_potential_seat_price_cents' => 2400000,
            'exceso_contribucion_status' => TripExcessContributionStatus::PENDIENTE,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->getJson(
            'api/admin/trip-excess-contributions?sort=user_name&direction=asc'
        )->assertOk();

        $names = collect($response->json('data'))->pluck('user_name')->all();
        $this->assertSame(['Alpha Driver', 'Beta Driver'], $names);
    }
}
