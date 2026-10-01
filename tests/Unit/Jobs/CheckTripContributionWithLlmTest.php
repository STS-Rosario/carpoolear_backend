<?php

namespace Tests\Unit\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Jobs\CheckTripContributionWithLlm;
use STS\Models\Trip;
use STS\Services\ContributionCheck\ContributionCheckFailedException;
use STS\Services\ContributionCheck\ContributionCheckHttpException;
use STS\Services\ContributionCheck\InvalidContributionCheckResponseException;
use Tests\TestCase;

class CheckTripContributionWithLlmTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'services.openrouter.timeout' => 30,
        ]);
    }

    private function trip(array $overrides = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'seat_price_cents' => 1500000,
            'description' => 'Son 24 lucas por persona, escribime al 341 555 1234',
            'recommended_trip_price_cents' => 5000000,
            'rear_max_two_passengers' => false,
        ], $overrides));
    }

    private function fakeAnswer(string $content): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);
    }

    private function runJob(Trip $trip): CheckTripContributionWithLlm
    {
        $job = (new CheckTripContributionWithLlm($trip->id))->withFakeQueueInteractions();
        $this->app->call([$job, 'handle']);

        return $job;
    }

    public function test_job_is_queued_with_retries_and_backoff(): void
    {
        $job = new CheckTripContributionWithLlm(1);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(1, $job->tripId);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff);
        $this->assertGreaterThan(config('services.openrouter.timeout'), $job->timeout);
    }

    public function test_persists_the_llm_result_on_the_trip(): void
    {
        $this->fakeAnswer('{"suspected_contribution": 24000, "exceeds_max": true, "phone_in_description": true}');
        $trip = $this->trip();

        $this->runJob($trip)->assertNotFailed();

        $trip->refresh();
        $this->assertTrue($trip->has_potential_excess_contribution);
        $this->assertSame(24000.0, $trip->suspected_contribution);
        $this->assertTrue($trip->phone_in_description);
        $this->assertSame(2400000, $trip->description_potential_seat_price_cents);
        Http::assertSentCount(1);
    }

    public function test_sends_the_trip_description_and_seat_price_as_max(): void
    {
        $this->fakeAnswer('{"suspected_contribution": null, "exceeds_max": false, "phone_in_description": false}');
        $trip = $this->trip();

        $this->runJob($trip);

        Http::assertSent(function ($request) {
            $prompt = collect($request->data()['messages'])->pluck('content')->implode("\n");

            return str_contains($prompt, 'Son 24 lucas por persona, escribime al 341 555 1234')
                && str_contains($prompt, '15000');
        });
    }

    public function test_skips_quietly_with_a_log_line_when_api_key_is_missing(): void
    {
        config(['services.openrouter.api_key' => '']);
        Http::fake();
        Log::spy();
        $trip = $this->trip();

        $this->runJob($trip)->assertNotFailed();

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context = []) => str_contains($message, 'OPENROUTER_API_KEY')
                && ($context['trip_id'] ?? null) === $trip->id
        );
        $this->assertFalse($trip->fresh()->has_potential_excess_contribution);
    }

    public function test_empty_description_clears_flags_without_calling_the_api(): void
    {
        Http::fake();
        $trip = $this->trip([
            'description' => '   ',
            'has_potential_excess_contribution' => true,
            'phone_in_description' => true,
        ]);

        $this->runJob($trip)->assertNotFailed();

        Http::assertNothingSent();
        $this->assertFalse($trip->fresh()->has_potential_excess_contribution);
        $this->assertFalse($trip->fresh()->phone_in_description);
    }

    public function test_deleted_trips_are_ignored(): void
    {
        Http::fake();
        $trip = $this->trip();
        $trip->delete();

        $this->runJob($trip)->assertNotFailed();

        Http::assertNothingSent();
    }

    public function test_server_errors_are_rethrown_so_the_queue_retries(): void
    {
        Http::fake(['openrouter.test/*' => Http::response('upstream down', 503)]);
        $trip = $this->trip();
        $job = (new CheckTripContributionWithLlm($trip->id))->withFakeQueueInteractions();

        try {
            $this->app->call([$job, 'handle']);
            $this->fail('Expected the job to rethrow for a retry.');
        } catch (ContributionCheckHttpException $e) {
            $this->assertSame(503, $e->status);
        }

        $job->assertNotFailed();
        $this->assertNull($trip->fresh()->suspected_contribution);
    }

    public function test_timeouts_are_rethrown_so_the_queue_retries(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ContributionCheckFailedException::class);

        $this->runJob($this->trip());
    }

    public function test_invalid_json_answers_are_rethrown_so_the_queue_retries(): void
    {
        $this->fakeAnswer('{"suspected_contribution": 24000, "exceeds_max": tr');

        $this->expectException(InvalidContributionCheckResponseException::class);

        $this->runJob($this->trip());
    }

    public function test_non_retryable_http_errors_fail_the_job_immediately(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $trip = $this->trip();

        $job = $this->runJob($trip);

        $job->assertFailed();
        $this->assertNull($trip->fresh()->suspected_contribution);
    }

    public function test_final_failure_is_logged(): void
    {
        Log::spy();

        (new CheckTripContributionWithLlm(42))->failed(new ContributionCheckFailedException('boom'));

        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context = []) => str_contains($message, 'contribution')
                && ($context['trip_id'] ?? null) === 42
                && ($context['error'] ?? null) === 'boom'
        );
    }
}
