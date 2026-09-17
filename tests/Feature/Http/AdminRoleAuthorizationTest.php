<?php

namespace Tests\Feature\Http;

use STS\Admin\AdminPermission;
use STS\Http\Middleware\UserAdmin;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\TripsManager;
use Tests\TestCase;

class AdminRoleAuthorizationTest extends TestCase
{
    private function helpdesk(): User
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $user->forceFill(['is_admin' => true, 'admin_role' => 'helpdesk'])->saveQuietly();

        return $user->fresh();
    }

    private function superadmin(): User
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $user->forceFill(['is_admin' => true, 'admin_role' => 'superadmin'])->saveQuietly();

        return $user->fresh();
    }

    private function actingAsStaff(User $user): void
    {
        $this->actingAs($user, 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    public function test_helpdesk_can_access_allowlisted_admin_surfaces(): void
    {
        $this->actingAsStaff($this->helpdesk());

        $this->getJson('api/admin/dashboard')->assertOk();
        $this->getJson('api/admin/users')->assertOk();
        $this->getJson('api/admin/user-migrations')->assertOk();
        $this->getJson('api/admin/users/account-delete-list')->assertOk();
        $this->getJson('api/admin/manual-identity-validations')->assertOk();
        $this->getJson('api/admin/mercado-pago-rejected-validations')->assertOk();
        $this->getJson('api/admin/support/tickets')->assertOk();
    }

    public function test_helpdesk_can_impersonate_a_regular_user(): void
    {
        $staff = $this->helpdesk();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);
        $this->actingAsStaff($staff);

        $this->postJson('api/admin/users/'.$target->id.'/impersonate')->assertCreated();
    }

    public function test_helpdesk_is_forbidden_from_destructive_and_unlisted_admin_routes(): void
    {
        $staff = $this->helpdesk();
        $target = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAsStaff($staff);

        $this->postJson('api/admin/users/'.$target->id.'/delete')->assertForbidden();
        $this->postJson('api/admin/users/'.$target->id.'/anonymize')->assertForbidden();
        $this->postJson('api/admin/users/'.$target->id.'/ban-and-anonymize')->assertForbidden();
        $this->postJson('api/admin/users/'.$target->id.'/clear-identity-validation')->assertForbidden();
        $this->getJson('api/admin/banned-users')->assertForbidden();
        $this->getJson('api/admin/maintenance/state')->assertForbidden();
        $this->getJson('api/admin/changelogs')->assertForbidden();
        $this->getJson('api/admin/car-brands')->assertForbidden();
        $this->getJson('api/admin/trip-excess-contributions')->assertForbidden();
        $this->getJson('api/admin/identity-verification-stats')->assertForbidden();
        $this->getJson('api/admin/badges')->assertForbidden();
        $this->postJson('api/admin/manual-identity-validations/1/purge')->assertForbidden();
        $this->patchJson('api/admin/ratings/1', ['comment' => 'nope'])->assertForbidden();
        $this->patchJson('api/admin/references/1', ['comment' => 'nope'])->assertForbidden();
    }

    public function test_superadmin_can_still_call_destructive_user_routes(): void
    {
        $staff = $this->superadmin();
        $target = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAsStaff($staff);

        $this->postJson('api/admin/users/'.$target->id.'/delete')
            ->assertOk()
            ->assertJsonPath('action', 'deleted');
    }

    public function test_helpdesk_can_edit_profile_fields_but_not_destructive_flags(): void
    {
        $staff = $this->helpdesk();
        $target = User::factory()->create([
            'active' => true,
            'banned' => false,
            'description' => 'before',
            'driver_is_verified' => false,
        ]);
        $this->actingAsStaff($staff);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'description' => 'helpdesk note',
            'banned' => true,
            'active' => false,
            'driver_is_verified' => true,
            'identity_validated' => true,
            'admin_role' => 'superadmin',
        ])->assertOk();

        $fresh = $target->fresh();
        $this->assertSame('helpdesk note', $fresh->description);
        $this->assertFalse((bool) $fresh->banned);
        $this->assertTrue((bool) $fresh->active);
        $this->assertFalse((bool) $fresh->driver_is_verified);
        $this->assertFalse((bool) $fresh->identity_validated);
        $this->assertNull($fresh->admin_role);
    }

    public function test_superadmin_can_suspend_via_admin_update(): void
    {
        $staff = $this->superadmin();
        $target = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAsStaff($staff);

        $this->putJson('api/users/modify', [
            'user' => ['id' => $target->id],
            'banned' => true,
        ])->assertOk();

        $this->assertTrue((bool) $target->fresh()->banned);
    }

    public function test_helpdesk_cannot_hide_another_users_trip(): void
    {
        $staff = $this->helpdesk();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $manager = app(TripsManager::class);
        $manager->changeVisibility($staff, $trip->id);

        $this->assertSame(trans('errors.tripowner'), $manager->getErrors());
        $this->assertNull($trip->fresh()->deleted_at);
    }

    public function test_superadmin_can_hide_another_users_trip(): void
    {
        $staff = $this->superadmin();
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $manager = app(TripsManager::class);
        $result = $manager->changeVisibility($staff, $trip->id);

        $this->assertNotNull($result);
        $this->assertNotNull($trip->fresh()->deleted_at);
    }

    public function test_helpdesk_permission_values_are_the_allowlist(): void
    {
        $staff = $this->helpdesk();

        $this->assertTrue($staff->can(AdminPermission::UsersEdit->value));
        $this->assertFalse($staff->can(AdminPermission::UsersDelete->value));
    }
}
