<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Http\Middleware\UserAdmin;
use STS\Models\IdentityVerificationEvent;
use STS\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class IdentityVerificationReportApiTest extends TestCase
{
    private const URL = 'api/admin/identity-verification-report';

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true, 'admin_role' => 'superadmin'])->saveQuietly();
        $this->actingAs($admin->fresh(), 'api');
        $this->withoutMiddleware(UserAdmin::class);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function event(array $attrs): IdentityVerificationEvent
    {
        return IdentityVerificationEvent::query()->create(array_merge([
            'created_at' => Carbon::parse('2026-09-20 12:00:00'),
        ], $attrs));
    }

    /**
     * Seeds an MP attempt_started and, optionally, its outcome.
     */
    private function mpAttempt(?int $userId, string $attemptId, ?string $outcome = null, ?string $reason = null, string $at = '2026-09-20 12:00:00', array $extra = []): void
    {
        $this->event(array_merge([
            'user_id' => $userId,
            'method' => 'mercado_pago',
            'name' => 'attempt_started',
            'attempt_id' => $attemptId,
            'created_at' => Carbon::parse($at),
        ], $extra));

        if ($outcome !== null) {
            $this->event(array_merge([
                'user_id' => $userId,
                'method' => 'mercado_pago',
                'name' => $outcome,
                'reason' => $reason,
                'attempt_id' => $attemptId,
                'created_at' => Carbon::parse($at)->addMinute(),
            ], $extra));
        }
    }

    private function uuid(int $n): string
    {
        return sprintf('00000000-0000-0000-0000-%012d', $n);
    }

    public function test_non_admin_is_rejected(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);
        $token = JWTAuth::fromUser($user);

        $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30', [
            'Authorization' => 'Bearer '.$token,
        ])->assertStatus(401);
    }

    public function test_validates_parameters(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URL)->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(self::URL.'?from=2026-09-30&to=2026-09-01')->assertUnprocessable()->assertJsonValidationErrors(['to']);
        $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30&group_by=year')->assertUnprocessable()->assertJsonValidationErrors(['group_by']);
        $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30&method=sms')->assertUnprocessable()->assertJsonValidationErrors(['method']);
    }

    public function test_echoes_filters_with_defaults(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('filters', [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
                'group_by' => 'month',
                'method' => 'all',
                'surface' => null,
                'platform' => null,
                'app_version' => null,
            ]);
    }

    public function test_classifies_each_mercado_pago_attempt_once(): void
    {
        $this->actingAsAdmin();
        $users = User::factory()->count(8)->create();

        $this->mpAttempt($users[0]->id, $this->uuid(1), 'succeeded');
        $this->mpAttempt($users[1]->id, $this->uuid(2), 'failed', 'dni_mismatch');
        $this->mpAttempt($users[2]->id, $this->uuid(3), 'failed', 'missing_identification');
        $this->mpAttempt($users[3]->id, $this->uuid(4), 'failed', 'token_exchange_failed');
        $this->mpAttempt($users[4]->id, $this->uuid(5), 'failed', 'oauth_cancelled');
        $this->mpAttempt($users[5]->id, $this->uuid(6));
        // Outside the range: ignored.
        $this->mpAttempt($users[6]->id, $this->uuid(7), 'succeeded', null, '2026-08-31 23:00:00');
        // Legacy attempt without attempt_id cannot be linked to an outcome: abandoned.
        $this->event(['user_id' => $users[7]->id, 'method' => 'mercado_pago', 'name' => 'attempt_started']);
        // Failure without attempt_id is not an attempt of its own.
        $this->event(['user_id' => null, 'method' => 'mercado_pago', 'name' => 'failed', 'reason' => 'missing_code_or_state']);

        $totals = $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30')->assertOk()->json('totals');

        $this->assertSame(7, $totals['attempts']);
        $this->assertSame(0, $totals['manual']['attempts']);
        $automatic = $totals['automatic'];
        $this->assertSame(7, $automatic['attempts']);
        $this->assertSame(['count' => 1, 'pct' => 14.29], $automatic['approved']);
        $this->assertSame(['count' => 2, 'pct' => 28.57], $automatic['rejected']);
        $this->assertSame(['count' => 1, 'pct' => 14.29], $automatic['error']);
        $this->assertSame(['count' => 1, 'pct' => 14.29], $automatic['cancelled']);
        $this->assertSame(['count' => 2, 'pct' => 28.57], $automatic['abandoned']);
    }

    public function test_empty_range_returns_zero_counts_and_zero_pct(): void
    {
        $this->actingAsAdmin();

        $totals = $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30')->assertOk()->json('totals');

        $this->assertSame(0, $totals['attempts']);
        $this->assertSame(['count' => 0, 'pct' => 0], $totals['automatic']['approved']);
        $this->assertSame(['count' => 0, 'pct' => 0], $totals['manual']['pending_review']);
    }
}
