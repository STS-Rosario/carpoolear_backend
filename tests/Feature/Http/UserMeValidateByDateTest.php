<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use STS\Models\User;
use Tests\TestCase;

class UserMeValidateByDateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sets_validate_by_for_pre_cutoff_user_on_first_me(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_days_for_current_users' => 30,
            'carpoolear.identity_validation_new_users_date' => '2025-06-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2020-01-01 00:00:00']);
        $user->refresh();

        $this->actingAs($user, 'api');
        $response = $this->get('api/users/me');
        $response->assertStatus(200);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['data']['validate_by_date'] ?? null);

        $user->refresh();
        $this->assertNotNull($user->validate_by_date);
    }

    public function test_does_not_set_validate_by_for_new_user_after_cutoff(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_days_for_current_users' => 30,
            'carpoolear.identity_validation_new_users_date' => '2020-01-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2025-06-15 00:00:00']);
        $user->refresh();

        $this->actingAs($user, 'api');
        $response = $this->get('api/users/me');
        $response->assertStatus(200);

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['data']['validate_by_date'] ?? null);

        $user->refresh();
        $this->assertNull($user->validate_by_date);
    }

    public function test_does_not_set_validate_by_when_optional_even_for_pre_cutoff_user(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => true,
            'carpoolear.identity_validation_days_for_current_users' => 30,
            'carpoolear.identity_validation_new_users_date' => '2025-06-01',
        ]);

        $user = User::factory()->create([
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        DB::table('users')->where('id', $user->id)->update(['created_at' => '2020-01-01 00:00:00']);
        $user->refresh();

        $this->actingAs($user, 'api');
        $response = $this->get('api/users/me');
        $response->assertStatus(200);

        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['data']['validate_by_date'] ?? null);

        $user->refresh();
        $this->assertNull($user->validate_by_date);
    }
}
