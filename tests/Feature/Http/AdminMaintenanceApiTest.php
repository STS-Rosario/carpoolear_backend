<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Routing\Middleware\ThrottleRequests;
use STS\Http\Middleware\UserAdmin;
use STS\Models\MaintenanceAuditLog;
use STS\Models\MaintenanceSchedule;
use STS\Models\MaintenanceState;
use STS\Models\User;
use STS\Services\Maintenance\MaintenanceStateService;
use Tests\TestCase;

class AdminMaintenanceApiTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_store_schedule_success_and_audits(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $payload = [
            'starts_at' => '2026-06-01T10:00:00Z',
            'ends_at' => '2026-06-01T12:00:00Z',
            'message' => 'DB work',
            'mode' => 'strict',
        ];

        $response = $this->postJson('api/admin/maintenance/schedules', $payload);
        $response->assertCreated();
        $this->assertDatabaseHas('maintenance_schedules', [
            'message' => 'DB work',
            'mode' => 'strict',
        ]);

        $this->assertTrue(
            MaintenanceAuditLog::query()->where('action', 'schedule_create')->where('user_id', $admin->id)->exists()
        );
    }

    public function test_store_rejects_overlap(): void
    {
        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-06-01 10:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-06-01 11:00:00 UTC'),
            'message' => 'First',
            'mode' => 'strict',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/maintenance/schedules', [
            'starts_at' => '2026-06-01T10:30:00Z',
            'ends_at' => '2026-06-01T12:00:00Z',
            'message' => 'Overlap',
            'mode' => 'flexible',
        ])->assertStatus(422)->assertJsonValidationErrors(['starts_at']);
    }

    public function test_adjacent_schedule_allowed(): void
    {
        MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-06-01 10:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-06-01 11:00:00 UTC'),
            'message' => 'First',
            'mode' => 'strict',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/maintenance/schedules', [
            'starts_at' => '2026-06-01T11:00:00Z',
            'ends_at' => '2026-06-01T12:00:00Z',
            'message' => 'Second',
            'mode' => 'strict',
        ])->assertCreated();
    }

    public function test_cancel_schedule_sets_cancelled_at(): void
    {
        $schedule = MaintenanceSchedule::query()->create([
            'starts_at' => Carbon::parse('2026-06-01 14:00:00 UTC'),
            'ends_at' => Carbon::parse('2026-06-01 15:00:00 UTC'),
            'message' => 'X',
            'mode' => 'strict',
        ]);

        $admin = $this->admin();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->deleteJson('api/admin/maintenance/schedules/'.$schedule->id)->assertOk();

        $schedule->refresh();
        $this->assertNotNull($schedule->cancelled_at);

        $this->assertTrue(
            MaintenanceAuditLog::query()->where('action', 'schedule_cancel')->where('user_id', $admin->id)->exists()
        );
    }

    public function test_put_state_disables_maintenance(): void
    {
        app(MaintenanceStateService::class)->applyManualActive(true, 'strict', 'x', null, 'manual', null, null);

        $admin = $this->admin();
        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->putJson('api/admin/maintenance/state', ['active' => false])->assertOk();

        $this->assertFalse(MaintenanceState::query()->find(1)->is_active);
    }
}
