<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Http\Middleware\UserAdmin;
use STS\Models\IdentityVerificationEvent;
use STS\Models\User;
use Tests\TestCase;

class IdentityVerificationStatsApiTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function event(array $attrs): IdentityVerificationEvent
    {
        return IdentityVerificationEvent::query()->create(array_merge([
            'created_at' => Carbon::parse('2026-09-10 12:00:00'),
        ], $attrs));
    }

    public function test_mp_stats_include_rates_reason_breakdown_and_abandonment(): void
    {
        Carbon::setTestNow('2026-09-17 12:00:00');
        $admin = $this->admin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $this->event([
            'user_id' => $userA->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        $this->event([
            'user_id' => $userA->id,
            'method' => 'mercado_pago',
            'name' => 'succeeded',
            'attempt_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
        $this->event([
            'user_id' => $userB->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ]);
        $this->event([
            'user_id' => $userB->id,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'dni_mismatch',
            'attempt_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        ]);
        $this->event([
            'user_id' => $userC->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/identity-verification-stats?from=2026-09-01&to=2026-09-30&method=mercado_pago')
            ->assertOk()
            ->json('data');

        $this->assertSame(3, $data['attempts']);
        $this->assertSame(1, $data['successes']);
        $this->assertSame(1, $data['failures']);
        $this->assertSame(1, $data['abandoned']);
        $this->assertSame(3, $data['unique_users_started']);
        $this->assertSame(1, $data['unique_users_succeeded']);
        $this->assertEquals(1.0, $data['retry_rate']);
        $this->assertEquals(33.33, $data['success_rate']);
        $this->assertEquals(33.33, $data['failure_rate']);
        $this->assertEquals(33.33, $data['abandonment_rate']);
        $this->assertCount(1, $data['failures_by_reason']);
        $this->assertSame('dni_mismatch', $data['failures_by_reason'][0]['reason']);
        $this->assertSame(1, $data['failures_by_reason'][0]['count']);
        $this->assertEquals(33.33, $data['failures_by_reason'][0]['percent_of_attempts']);
        $this->assertEquals(100.0, $data['failures_by_reason'][0]['percent_of_failures']);
    }

    public function test_manual_stats_use_docs_submitted_as_attempts(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->event([
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'payment_started',
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'docs_submitted',
            'related_id' => 9,
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'manual',
            'name' => 'failed',
            'reason' => 'docs_illegible',
            'related_id' => 9,
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/identity-verification-stats?from=2026-09-01&to=2026-09-30&method=manual')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['attempts']);
        $this->assertSame(0, $data['successes']);
        $this->assertSame(1, $data['failures']);
        $this->assertSame(0, $data['abandoned']);
        $this->assertEquals(100.0, $data['failure_rate']);
        $this->assertSame('docs_illegible', $data['failures_by_reason'][0]['reason']);
    }

    public function test_date_range_and_optional_filters(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'surface' => 'choice_cards',
            'platform' => 'android',
            'app_version' => '4.1.0',
            'created_at' => Carbon::parse('2026-08-01 12:00:00'),
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'surface' => 'choice_cards',
            'platform' => 'android',
            'app_version' => '4.1.0',
            'created_at' => Carbon::parse('2026-09-10 12:00:00'),
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'surface' => 'pending_switch',
            'platform' => 'web',
            'app_version' => '4.0.0',
            'created_at' => Carbon::parse('2026-09-10 13:00:00'),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/identity-verification-stats?from=2026-09-01&to=2026-09-30&method=mercado_pago&surface=choice_cards&platform=android&app_version=4.1.0')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['attempts']);
    }

    public function test_group_by_day_returns_series(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'created_at' => Carbon::parse('2026-09-10 08:00:00'),
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'succeeded',
            'created_at' => Carbon::parse('2026-09-10 09:00:00'),
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'created_at' => Carbon::parse('2026-09-11 08:00:00'),
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/identity-verification-stats?from=2026-09-01&to=2026-09-30&method=mercado_pago&group_by=day')
            ->assertOk()
            ->json('data');

        $this->assertSame('2026-09-10', $data['series'][0]['period']);
        $this->assertSame(1, $data['series'][0]['attempts']);
        $this->assertSame(1, $data['series'][0]['successes']);
        $this->assertSame('2026-09-11', $data['series'][1]['period']);
        $this->assertSame(1, $data['series'][1]['attempts']);
        $this->assertSame(0, $data['series'][1]['successes']);
    }

    public function test_retry_rate_uses_attempts_over_unique_users(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => '11111111-1111-1111-1111-111111111111',
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'failed',
            'reason' => 'oauth_cancelled',
            'attempt_id' => '11111111-1111-1111-1111-111111111111',
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => '22222222-2222-2222-2222-222222222222',
        ]);
        $this->event([
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'succeeded',
            'attempt_id' => '22222222-2222-2222-2222-222222222222',
        ]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $data = $this->getJson('api/admin/identity-verification-stats?from=2026-09-01&to=2026-09-30&method=mercado_pago')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['attempts']);
        $this->assertSame(1, $data['unique_users_started']);
        $this->assertEquals(2.0, $data['retry_rate']);
    }
}
