<?php

namespace Tests\Unit\Services\ContributionCheck;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use STS\Services\ContributionCheck\ContributionCheckFailedException;
use STS\Services\ContributionCheck\ContributionCheckHttpException;
use STS\Services\ContributionCheck\InvalidContributionCheckResponseException;
use STS\Services\ContributionCheck\OpenRouterContributionChecker;
use Tests\TestCase;

class OpenRouterContributionCheckerTest extends TestCase
{
    private const DESCRIPTION = 'Salgo temprano. La contribución es de 24 lucas por persona. Escribime al 341 555 1234';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'arg',
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.openrouter.base_url' => 'https://openrouter.test/api/v1',
            'services.openrouter.timeout' => 12,
            'services.openrouter.contribution_check.model' => 'deepseek/deepseek-v4.1-flash',
            'services.openrouter.contribution_check.reasoning' => 'off',
        ]);
    }

    private function checker(): OpenRouterContributionChecker
    {
        return $this->app->make(OpenRouterContributionChecker::class);
    }

    private function fakeAnswer(string $content = '{"suspected_contribution": 24000, "exceeds_max": true, "phone_in_description": true}'): void
    {
        Http::fake([
            'openrouter.test/*' => Http::response([
                'id' => 'gen-1',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => $content]],
                ],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentPayload(): array
    {
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);

        return $recorded[0][0]->data();
    }

    private function sentPrompt(): string
    {
        return collect($this->sentPayload()['messages'])->pluck('content')->implode("\n");
    }

    public function test_is_configured_only_with_an_api_key(): void
    {
        $this->assertTrue($this->checker()->isConfigured());

        config(['services.openrouter.api_key' => '']);
        $this->assertFalse($this->checker()->isConfigured());

        config(['services.openrouter.api_key' => null]);
        $this->assertFalse($this->checker()->isConfigured());
    }

    public function test_posts_to_chat_completions_with_bearer_token(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://openrouter.test/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-openrouter-key');
        });
    }

    public function test_payload_uses_configured_model_json_format_and_zero_temperature(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $payload = $this->sentPayload();
        $this->assertSame('deepseek/deepseek-v4.1-flash', $payload['model']);
        $this->assertSame(['type' => 'json_object'], $payload['response_format']);
        $this->assertSame(0, $payload['temperature']);
    }

    public function test_reasoning_is_disabled_by_default(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $this->assertSame(['enabled' => false], $this->sentPayload()['reasoning']);
    }

    public function test_reasoning_can_be_set_to_low_effort(): void
    {
        config(['services.openrouter.contribution_check.reasoning' => 'low']);
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $this->assertSame(['effort' => 'low'], $this->sentPayload()['reasoning']);
    }

    public function test_request_uses_configured_timeout(): void
    {
        $timeout = null;
        Http::fake(function (Request $request, array $options) use (&$timeout) {
            $timeout = $options['timeout'] ?? null;

            return Http::response([
                'choices' => [['message' => ['content' => '{"suspected_contribution": null, "exceeds_max": false, "phone_in_description": false}']]],
            ]);
        });

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $this->assertEquals(12, $timeout);
    }

    public function test_prompt_includes_description_max_contribution_and_currency(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString(self::DESCRIPTION, $prompt);
        $this->assertStringContainsString('15000', $prompt);
        $this->assertStringContainsString('ARS', $prompt);
    }

    public function test_prompt_uses_chilean_pesos_for_the_chilean_locale(): void
    {
        config(['app.locale' => 'chl']);
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $this->assertStringContainsString('CLP', $this->sentPrompt());
    }

    public function test_prompt_asks_for_excess_phone_and_strict_json_keys(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, 1500000);

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString('teléfono', $prompt);
        $this->assertStringContainsString('"suspected_contribution"', $prompt);
        $this->assertStringContainsString('"exceeds_max"', $prompt);
        $this->assertStringContainsString('"phone_in_description"', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
        $this->assertStringContainsString('mayor a 1 y menor a 2000', $prompt);
        $this->assertStringContainsString('no lo multipliques', $prompt);
    }

    public function test_prompt_states_there_is_no_max_for_voluntary_contributions(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, -1);

        $this->assertStringContainsString('no tiene una contribución máxima', $this->sentPrompt());
    }

    public function test_prompt_states_there_is_no_max_when_none_is_given(): void
    {
        $this->fakeAnswer();

        $this->checker()->check(self::DESCRIPTION, null);

        $this->assertStringContainsString('no tiene una contribución máxima', $this->sentPrompt());
    }

    public function test_returns_the_parsed_answer(): void
    {
        $this->fakeAnswer("```json\n{\"suspected_contribution\": 24000, \"exceeds_max\": true, \"phone_in_description\": true}\n```");

        $result = $this->checker()->check(self::DESCRIPTION, 1500000);

        $this->assertSame(24000.0, $result->suspectedContribution);
        $this->assertTrue($result->exceedsMax);
        $this->assertTrue($result->phoneInDescription);
    }

    public function test_server_errors_are_retryable_http_failures(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['error' => ['message' => 'upstream']], 502)]);

        try {
            $this->checker()->check(self::DESCRIPTION, 1500000);
            $this->fail('Expected an HTTP failure.');
        } catch (ContributionCheckHttpException $e) {
            $this->assertSame(502, $e->status);
            $this->assertTrue($e->isRetryable());
        }
    }

    public function test_rate_limits_are_retryable_and_auth_errors_are_not(): void
    {
        Http::fakeSequence('openrouter.test/*')
            ->push(['error' => ['message' => 'slow down']], 429)
            ->push(['error' => ['message' => 'bad key']], 401);

        try {
            $this->checker()->check(self::DESCRIPTION, 1500000);
            $this->fail('Expected an HTTP failure.');
        } catch (ContributionCheckHttpException $e) {
            $this->assertTrue($e->isRetryable());
        }

        try {
            $this->checker()->check(self::DESCRIPTION, 1500000);
            $this->fail('Expected an HTTP failure.');
        } catch (ContributionCheckHttpException $e) {
            $this->assertSame(401, $e->status);
            $this->assertFalse($e->isRetryable());
        }
    }

    public function test_timeouts_raise_a_contribution_check_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ContributionCheckFailedException::class);

        $this->checker()->check(self::DESCRIPTION, 1500000);
    }

    public function test_answers_without_message_content_are_invalid(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(['choices' => []])]);

        $this->expectException(InvalidContributionCheckResponseException::class);

        $this->checker()->check(self::DESCRIPTION, 1500000);
    }

    public function test_non_json_answers_are_invalid(): void
    {
        $this->fakeAnswer('No puedo responder eso.');

        $this->expectException(InvalidContributionCheckResponseException::class);

        $this->checker()->check(self::DESCRIPTION, 1500000);
    }
}
