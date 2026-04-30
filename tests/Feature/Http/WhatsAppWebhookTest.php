<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

/**
 * Behavioural coverage for {@see \STS\Http\Controllers\Api\v1\WhatsAppWebhookController}
 * on {@code Route::any('/webhooks/whatsapp', …)} (see {@code routes/web.php}).
 *
 * Query keys follow what the controller reads ({@code hub_mode}, {@code hub_verify_token}, {@code hub_challenge}).
 * Signed POSTs use the raw body bytes so {@code X-Hub-Signature-256} matches Meta’s HMAC contract.
 */
class WhatsAppWebhookTest extends TestCase
{
    private function signedPost(string $rawBody, string $appSecret, ?string $signatureOverride = null): \Illuminate\Testing\TestResponse
    {
        $signature = $signatureOverride ?? ('sha256='.hash_hmac('sha256', $rawBody, $appSecret));

        return $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $rawBody
        );
    }

    public function test_unsupported_http_method_returns_method_not_allowed_json(): void
    {
        $this->delete('/webhooks/whatsapp')
            ->assertStatus(405)
            ->assertExactJson(['error' => 'Method not allowed']);
    }

    public function test_get_verification_returns_plaintext_challenge_when_mode_and_token_match(): void
    {
        $token = 'verify-token-'.uniqid();
        config(['services.whatsapp.verify_token' => $token]);

        $challenge = 'meta-challenge-'.uniqid();

        $this->get('/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $token,
            'hub_challenge' => $challenge,
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee($challenge, false);
    }

    public function test_get_verification_returns_forbidden_when_verify_token_mismatches(): void
    {
        config(['services.whatsapp.verify_token' => 'expected-token']);

        $this->get('/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => 'should-not-be-returned',
        ]))
            ->assertForbidden()
            ->assertSee('Forbidden', false);
    }

    public function test_get_verification_returns_forbidden_when_mode_is_not_subscribe(): void
    {
        config(['services.whatsapp.verify_token' => 'expected-token']);

        $this->get('/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'unsubscribe',
            'hub_verify_token' => 'expected-token',
            'hub_challenge' => 'ignored',
        ]))
            ->assertForbidden();
    }

    public function test_post_without_signature_returns_unauthorized(): void
    {
        config([
            'services.whatsapp.app_secret' => 'configured-secret',
        ]);

        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []]);

        $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body
        )
            ->assertUnauthorized()
            ->assertSee('Unauthorized', false);
    }

    public function test_post_when_app_secret_not_configured_accepts_request_with_any_non_empty_signature(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=ignored-when-no-app-secret',
            ],
            $body
        )
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }

    public function test_post_with_app_secret_rejects_wrong_signature(): void
    {
        $secret = 'app-secret-'.uniqid();
        config(['services.whatsapp.app_secret' => $secret]);

        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);

        $this->signedPost($body, $secret, 'sha256='.str_repeat('00', 32))
            ->assertUnauthorized();
    }

    public function test_post_with_app_secret_accepts_valid_hmac_and_returns_success_json(): void
    {
        $secret = 'app-secret-'.uniqid();
        config(['services.whatsapp.app_secret' => $secret]);

        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => []], JSON_THROW_ON_ERROR);

        $this->signedPost($body, $secret)
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }

    public function test_post_with_wrong_object_type_still_returns_success_so_meta_does_not_retry(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $body = json_encode(['object' => 'page', 'entry' => []], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=placeholder',
            ],
            $body
        )
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }

    public function test_post_processes_messages_message_status_and_unknown_change_fields(): void
    {
        config(['services.whatsapp.app_secret' => null]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'waba-test-id',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messages' => [
                                    ['id' => 'wamid-1', 'from' => '5491100000000', 'type' => 'text'],
                                ],
                            ],
                        ],
                        [
                            'field' => 'message_status',
                            'value' => [
                                'statuses' => [
                                    ['id' => 'wamid-1', 'status' => 'delivered'],
                                ],
                            ],
                        ],
                        [
                            'field' => 'account_update',
                            'value' => [],
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/webhooks/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256=placeholder',
            ],
            $body
        )
            ->assertOk()
            ->assertExactJson(['success' => true]);
    }
}
