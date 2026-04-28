<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use STS\Helpers\IdentityValidationHelper;
use STS\Models\User;
use Tests\TestCase;

class IdentityValidationHelperTest extends TestCase
{
    use DatabaseTransactions;

    public function test_when_optional_enforcement_is_off_all_unvalidated_users_can_act(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => true,
        ]);

        $user = User::factory()->create(['identity_validated' => false, 'validate_by_date' => null]);

        $this->assertTrue(IdentityValidationHelper::canPerformRestrictedActions($user));
    }

    public function test_new_user_is_blocked_when_enforced_and_required_flag_on(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create(['identity_validated' => false, 'validate_by_date' => null]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($user));
        $this->assertTrue(IdentityValidationHelper::isNewUserRequiringValidation($user));
        $this->assertFalse(IdentityValidationHelper::canPerformRestrictedActions($user));
    }

    public function test_pre_cutoff_user_with_past_validate_by_is_blocked(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => Carbon::now()->subDays(2)->toDateString(),
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($user));
        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
        $this->assertTrue(IdentityValidationHelper::isCurrentUserPastDeadline($user));
        $this->assertFalse(IdentityValidationHelper::canPerformRestrictedActions($user));
    }

    public function test_is_user_created_on_or_after_cutoff_respects_date(): void
    {
        config(['carpoolear.identity_validation_new_users_date' => '2024-01-01']);

        $old = User::factory()->create();
        DB::table('users')->where('id', $old->id)->update(['created_at' => '2020-01-01 00:00:00']);
        $old->refresh();

        $new = User::factory()->create();
        DB::table('users')->where('id', $new->id)->update(['created_at' => '2024-02-01 00:00:00']);
        $new->refresh();

        $this->assertFalse(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($old));
        $this->assertTrue(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($new));
    }

    public function test_identity_validated_user_is_always_allowed_when_feature_enabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => true,
            'validate_by_date' => Carbon::now()->subWeek()->toDateString(),
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2025-01-01 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::canPerformRestrictedActions($user));
    }

    public function test_feature_disabled_allows_users_even_if_not_validated_and_past_deadline(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => false,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => Carbon::now()->subDay()->toDateString(),
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2025-01-01 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::canPerformRestrictedActions($user));
    }
}
