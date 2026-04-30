<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Helpers\IdentityValidationHelper;
use STS\Models\User;
use Tests\TestCase;

class IdentityValidationHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_enforcement_is_active_when_enabled_true_and_optional_key_absent(): void
    {
        $carpoolear = config('carpoolear');
        unset($carpoolear['identity_validation_optional']);
        $carpoolear['identity_validation_enabled'] = true;
        config(['carpoolear' => $carpoolear]);

        $this->assertTrue(IdentityValidationHelper::enforcementIsActive());
    }

    public function test_enforcement_is_inactive_when_master_flag_disabled_even_if_optional_missing(): void
    {
        $carpoolear = config('carpoolear');
        unset($carpoolear['identity_validation_enabled'], $carpoolear['identity_validation_optional']);
        config(['carpoolear' => $carpoolear]);

        $this->assertFalse(IdentityValidationHelper::enforcementIsActive());
    }

    public function test_enforcement_is_inactive_when_optional_even_if_enabled(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => true,
        ]);

        $this->assertFalse(IdentityValidationHelper::enforcementIsActive());
    }

    public function test_new_users_cutoff_date_is_null_for_empty_config(): void
    {
        config(['carpoolear.identity_validation_new_users_date' => null]);
        $this->assertNull(IdentityValidationHelper::newUsersCutoffDate());

        config(['carpoolear.identity_validation_new_users_date' => '']);
        $this->assertNull(IdentityValidationHelper::newUsersCutoffDate());
    }

    public function test_new_users_cutoff_date_parses_to_start_of_day(): void
    {
        config(['carpoolear.identity_validation_new_users_date' => '2024-03-15']);
        $cutoff = IdentityValidationHelper::newUsersCutoffDate();
        $this->assertInstanceOf(Carbon::class, $cutoff);
        $this->assertSame('2024-03-15 00:00:00', $cutoff->format('Y-m-d H:i:s'));
    }

    public function test_is_user_created_on_or_after_cutoff_false_without_cutoff(): void
    {
        config(['carpoolear.identity_validation_new_users_date' => null]);
        $user = User::factory()->create();
        $this->assertFalse(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($user));
    }

    public function test_is_new_user_requiring_validation_false_when_enforcement_inactive(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => false,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create(['identity_validated' => false, 'validate_by_date' => null]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
    }

    public function test_is_new_user_requiring_validation_false_when_required_flag_absent(): void
    {
        $carpoolear = config('carpoolear');
        unset($carpoolear['identity_validation_required_new_users']);
        $carpoolear['identity_validation_enabled'] = true;
        $carpoolear['identity_validation_optional'] = false;
        $carpoolear['identity_validation_new_users_date'] = '2020-01-01';
        config(['carpoolear' => $carpoolear]);

        $user = User::factory()->create(['identity_validated' => false, 'validate_by_date' => null]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
    }

    public function test_is_new_user_requiring_validation_false_when_user_before_cutoff(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create(['identity_validated' => false, 'validate_by_date' => null]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($user));
        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
    }

    public function test_is_new_user_requiring_validation_false_when_grace_deadline_is_set(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => Carbon::now()->addMonth()->toDateString(),
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
    }

    public function test_is_new_user_requiring_validation_false_when_already_validated(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => true,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isNewUserRequiringValidation($user));
    }

    public function test_is_current_user_past_deadline_false_without_validate_by_date(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isCurrentUserPastDeadline($user));
    }

    public function test_is_current_user_past_deadline_false_when_same_calendar_day_as_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-10 14:00:00', 'UTC'));

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => '2025-06-10',
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isCurrentUserPastDeadline($user));
    }

    public function test_is_current_user_past_deadline_true_after_end_of_deadline_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-11 00:00:01', 'UTC'));

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => '2025-06-10',
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::isCurrentUserPastDeadline($user));
    }

    public function test_is_current_user_past_deadline_false_for_new_users_on_or_after_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-20 12:00:00', 'UTC'));

        config(['carpoolear.identity_validation_new_users_date' => '2020-01-01']);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => '2025-06-01',
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2021-06-15 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::isUserCreatedOnOrAfterCutoff($user));
        $this->assertFalse(IdentityValidationHelper::isCurrentUserPastDeadline($user));
    }

    public function test_is_current_user_past_deadline_false_when_identity_validated(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-11 12:00:00', 'UTC'));

        $user = User::factory()->create([
            'identity_validated' => true,
            'validate_by_date' => '2025-06-01',
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertFalse(IdentityValidationHelper::isCurrentUserPastDeadline($user));
    }

    public function test_legacy_user_before_cutoff_can_act_under_strict_new_user_rules(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2019-06-15 00:00:00']);
        $user->refresh();

        $this->assertTrue(IdentityValidationHelper::canPerformRestrictedActions($user));
    }

    public function test_identity_validation_required_error_shape(): void
    {
        $this->assertSame(
            ['error' => ['identity_validation_required']],
            IdentityValidationHelper::identityValidationRequiredError()
        );
    }

    public function test_identity_validation_required_message_is_stable(): void
    {
        $this->assertSame(
            'You must verify your account to perform this action.',
            IdentityValidationHelper::identityValidationRequiredMessage()
        );
    }

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
