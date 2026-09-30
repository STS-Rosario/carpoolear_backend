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

    /**
     * Seeds the event history of one manual request, one minute apart.
     *
     * @param  list<string|array{0: string, 1: string}>  $events  name, or [name, reason]
     */
    private function manualRequest(int $userId, int $requestId, array $events, string $start = '2026-09-20 10:00:00', string $relatedType = 'manual_identity_validations'): void
    {
        $at = Carbon::parse($start);
        foreach ($events as $event) {
            [$name, $reason] = is_array($event) ? $event : [$event, null];
            $this->event([
                'user_id' => $userId,
                'method' => 'manual',
                'name' => $name,
                'reason' => $reason,
                'related_type' => $relatedType,
                'related_id' => $requestId,
                'created_at' => $at->copy(),
            ]);
            $at->addMinute();
        }
    }

    public function test_classifies_each_paid_manual_request_once_by_its_latest_state(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        // Paid, never sent documents: inconclusive.
        $this->manualRequest($user->id, 1, ['payment_started', 'payment_succeeded']);
        // Waiting for an admin: pending_review.
        $this->manualRequest($user->id, 2, ['payment_succeeded', 'docs_submitted']);
        // Rejected, resubmitted on the same row, then approved: one attempt, approved.
        $this->manualRequest($user->id, 3, ['payment_succeeded', 'docs_submitted', ['failed', 'docs_illegible'], 'docs_submitted', 'succeeded']);
        // Rejected.
        $this->manualRequest($user->id, 4, ['payment_succeeded', 'docs_submitted', ['failed', 'selfie_mismatch'], 'upload_rejected']);
        // Asked for more info, never re-sent: inconclusive.
        $this->manualRequest($user->id, 5, ['payment_succeeded', 'docs_submitted', 'info_requested']);
        // Closed without a decision after an MP success: inconclusive.
        $this->manualRequest($user->id, 6, ['payment_succeeded', 'docs_submitted', 'closed_after_mp_success']);
        // Never paid: not an attempt (even when later closed).
        $this->manualRequest($user->id, 7, ['payment_started', 'closed_after_mp_success']);
        // Admin override to approved.
        $this->manualRequest($user->id, 8, ['payment_succeeded', 'docs_submitted', ['admin_state_changed', 'approved']]);
        // Approval of an MP rejection is not a manual request.
        $this->manualRequest($user->id, 9, [['succeeded', 'approved_from_mp_rejection']], '2026-09-20 10:00:00', 'mercado_pago_rejected_validations');
        // Paid before the range: belongs to August even if documents arrive in September.
        $this->manualRequest($user->id, 10, ['payment_succeeded'], '2026-08-31 23:59:00');
        $this->manualRequest($user->id, 10, ['docs_submitted'], '2026-09-02 10:00:00');

        $totals = $this->getJson(self::URL.'?from=2026-09-01&to=2026-09-30')->assertOk()->json('totals');

        $this->assertSame(7, $totals['attempts']);
        $manual = $totals['manual'];
        $this->assertSame(7, $manual['attempts']);
        $this->assertSame(['count' => 2, 'pct' => 28.57], $manual['approved']);
        $this->assertSame(['count' => 1, 'pct' => 14.29], $manual['rejected']);
        $this->assertSame(['count' => 3, 'pct' => 42.86], $manual['inconclusive']);
        $this->assertSame(['count' => 1, 'pct' => 14.29], $manual['pending_review']);
    }

    public function test_series_groups_by_month_by_default_and_fills_empty_periods(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->mpAttempt($user->id, $this->uuid(1), 'succeeded', null, '2026-08-05 10:00:00');
        $this->mpAttempt($user->id, $this->uuid(2), 'failed', 'dni_mismatch', '2026-08-06 10:00:00');
        $this->manualRequest($user->id, 1, ['payment_succeeded', 'docs_submitted'], '2026-10-10 10:00:00');

        $data = $this->getJson(self::URL.'?from=2026-08-01&to=2026-10-31')->assertOk()->json();

        $this->assertSame(['2026-08', '2026-09', '2026-10'], array_column($data['series'], 'period'));

        $august = $data['series'][0];
        $this->assertSame(2, $august['attempts']);
        $this->assertSame(2, $august['automatic']['attempts']);
        $this->assertSame(['count' => 1, 'pct' => 50], $august['automatic']['approved']);
        $this->assertSame(['count' => 1, 'pct' => 50], $august['automatic']['rejected']);
        $this->assertSame(0, $august['manual']['attempts']);

        $september = $data['series'][1];
        $this->assertSame(0, $september['attempts']);
        $this->assertSame(['count' => 0, 'pct' => 0], $september['automatic']['abandoned']);
        $this->assertSame(['count' => 0, 'pct' => 0], $september['manual']['inconclusive']);

        $october = $data['series'][2];
        $this->assertSame(1, $october['attempts']);
        $this->assertSame(['count' => 1, 'pct' => 100], $october['manual']['pending_review']);

        $this->assertSame(3, $data['totals']['attempts']);
    }

    public function test_series_groups_by_iso_week_starting_monday(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->mpAttempt($user->id, $this->uuid(1), 'succeeded', null, '2026-09-14 00:30:00');
        $this->mpAttempt($user->id, $this->uuid(2), null, null, '2026-09-20 23:00:00');
        $this->mpAttempt($user->id, $this->uuid(3), 'failed', 'oauth_denied', '2026-09-21 09:00:00');

        $series = $this->getJson(self::URL.'?from=2026-09-15&to=2026-09-22&group_by=week')->assertOk()->json('series');

        $this->assertSame(['2026-09-14', '2026-09-21'], array_column($series, 'period'));
        $this->assertSame(1, $series[0]['attempts']);
        $this->assertSame(['count' => 1, 'pct' => 100], $series[0]['automatic']['abandoned']);
        $this->assertSame(['count' => 1, 'pct' => 100], $series[1]['automatic']['cancelled']);
    }

    public function test_series_groups_by_day(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create();

        $this->mpAttempt($user->id, $this->uuid(1), 'succeeded', null, '2026-09-20 23:59:00');
        $this->mpAttempt($user->id, $this->uuid(2), 'succeeded', null, '2026-09-22 00:00:00');

        $series = $this->getJson(self::URL.'?from=2026-09-20&to=2026-09-22&group_by=day')->assertOk()->json('series');

        $this->assertSame(['2026-09-20', '2026-09-21', '2026-09-22'], array_column($series, 'period'));
        $this->assertSame([1, 0, 1], array_column($series, 'attempts'));
    }
}
