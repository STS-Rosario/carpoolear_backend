<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\DB;
use STS\Admin\AdminPermission;
use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminPermission as AdminPermissionModel;
use STS\Models\SupportTicket;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

/**
 * Excess-contribution help desk tickets are linked to a trip, and a trip can only have one.
 */
class AdminSupportTicketExcessTripTest extends TestCase
{
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $admin = $admin->fresh();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        return $admin;
    }

    private function grantAdminPermission(User $admin, AdminPermission $permission): void
    {
        $permissionId = DB::table('admin_permissions')->where('name', $permission->value)->value('id');
        if ($permissionId === null) {
            $permissionId = AdminPermissionModel::query()->where('name', $permission->value)->value('id');
        }
        if ($permissionId !== null) {
            DB::table('admin_user_permission')->updateOrInsert([
                'user_id' => $admin->id,
                'admin_permission_id' => $permissionId,
            ], []);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function excessPayload(User $owner, ?Trip $trip, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $owner->id,
            'type' => 'excess_contribution',
            'subject' => 'Exceso de contribución',
            'message_markdown' => 'Just a test.',
            'trip_id' => $trip?->id,
        ], $overrides);
    }

    private function excessTicketsForTrip(Trip $trip): int
    {
        return SupportTicket::query()
            ->where('type', 'excess_contribution')
            ->where('trip_id', $trip->id)
            ->count();
    }

    public function test_admin_created_ticket_stores_the_trip_id(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))
            ->assertOk();

        $this->assertSame($trip->id, (int) $response->json('data.trip_id'));
        $this->assertDatabaseHas('support_tickets', [
            'id' => (int) $response->json('data.id'),
            'trip_id' => $trip->id,
            'type' => 'excess_contribution',
        ]);
    }

    public function test_second_excess_ticket_for_the_same_trip_is_rejected_with_conflict(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $firstId = (int) $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))
            ->assertOk()
            ->json('data.id');

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, [
            'message_markdown' => 'Another message',
        ]))
            ->assertStatus(409)
            ->assertJsonPath('error', 'This trip already has an excess contribution ticket.')
            ->assertJsonPath('existing_ticket_id', $firstId);

        $this->assertSame(1, $this->excessTicketsForTrip($trip));
    }

    public function test_a_closed_excess_ticket_still_blocks_a_new_one_for_the_trip(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);
        $closed = SupportTicket::create([
            'user_id' => $owner->id,
            'trip_id' => $trip->id,
            'type' => 'excess_contribution',
            'subject' => 'Exceso de contribución',
            'status' => 'Cerrado',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))
            ->assertStatus(409)
            ->assertJsonPath('existing_ticket_id', $closed->id);

        $this->assertSame(1, $this->excessTicketsForTrip($trip));
    }

    public function test_excess_tickets_for_different_trips_are_allowed(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $firstTrip = Trip::factory()->create(['user_id' => $owner->id]);
        $secondTrip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $firstTrip))->assertOk();
        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $secondTrip))->assertOk();

        $this->assertSame(1, $this->excessTicketsForTrip($firstTrip));
        $this->assertSame(1, $this->excessTicketsForTrip($secondTrip));
    }

    public function test_other_ticket_types_are_not_limited_per_trip(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, ['type' => 'contact']))->assertOk();
        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, [
            'type' => 'contact',
            'message_markdown' => 'Second contact',
        ]))->assertOk();
        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))->assertOk();
    }

    public function test_excess_tickets_without_a_trip_keep_working(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();

        $response = $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, null))->assertOk();
        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, null, [
            'message_markdown' => 'Second one without trip',
        ]))->assertOk();

        $this->assertNull($response->json('data.trip_id'));
    }

    public function test_trip_id_must_exist(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, null, ['trip_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id']);
    }

    public function test_trip_must_belong_to_the_ticket_user(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $otherDriver = User::factory()->create();
        $otherTrip = Trip::factory()->create(['user_id' => $otherDriver->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $otherTrip))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id']);

        $this->assertSame(0, SupportTicket::query()->where('trip_id', $otherTrip->id)->count());
    }

    public function test_excess_contribution_detail_exposes_the_trip_ticket_id(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $flagged = ['user_id' => $owner->id, 'has_potential_excess_contribution' => true];
        $trip = Trip::factory()->create($flagged);
        $tripWithoutTicket = Trip::factory()->create($flagged);
        SupportTicket::create([
            'user_id' => $owner->id,
            'trip_id' => $tripWithoutTicket->id,
            'type' => 'contact',
            'subject' => 'Not an excess ticket',
            'status' => 'Open',
            'priority' => 'normal',
            'unread_for_user' => 0,
            'unread_for_admin' => 0,
        ]);
        $ticketId = (int) $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))
            ->assertOk()
            ->json('data.id');

        $this->getJson('api/admin/trip-excess-contributions/'.$trip->id)
            ->assertOk()
            ->assertJsonPath('data.excess_contribution_ticket_id', $ticketId);
        $this->getJson('api/admin/trip-excess-contributions/'.$tripWithoutTicket->id)
            ->assertOk()
            ->assertJsonPath('data.excess_contribution_ticket_id', null);
    }

    public function test_can_suspend_user_when_creating_excess_contribution_ticket_with_permission(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveQuietly();
        $this->grantAdminPermission($admin, \STS\Admin\AdminPermission::UsersSuspend);
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $owner = User::factory()->create(['banned' => 0]);
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, ['suspend_user' => true]))
            ->assertOk()
            ->assertJsonPath('user_suspended', true);

        $owner->refresh();
        $this->assertSame(1, (int) $owner->banned);
    }

    public function test_creating_excess_contribution_without_suspend_user_does_not_change_banned_status(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create(['banned' => 0]);
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $response = $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip))
            ->assertOk();

        $owner->refresh();
        $this->assertSame(0, (int) $owner->banned);
    }

    public function test_cannot_suspend_user_when_creating_excess_contribution_ticket_without_permission(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create(['banned' => 0]);
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, ['suspend_user' => true]))
            ->assertStatus(403);

        $owner->refresh();
        $this->assertSame(0, (int) $owner->banned);
    }

    public function test_can_update_user_private_note_when_creating_excess_contribution_ticket(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, ['private_note' => '  Seguimiento por exceso de contribución  ']))
            ->assertOk()
            ->assertJsonPath('private_note_updated', true);

        $owner->refresh();
        $this->assertSame('Seguimiento por exceso de contribución', $owner->private_note);
    }

    public function test_empty_private_note_does_not_update_user_private_note(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create(['private_note' => 'nota original']);
        $trip = Trip::factory()->create(['user_id' => $owner->id]);

        $this->postJson('api/admin/support/tickets', $this->excessPayload($owner, $trip, ['private_note' => '   ']))
            ->assertOk()
            ->assertJsonPath('private_note_updated', false);

        $owner->refresh();
        $this->assertSame('nota original', $owner->private_note);
    }
}
