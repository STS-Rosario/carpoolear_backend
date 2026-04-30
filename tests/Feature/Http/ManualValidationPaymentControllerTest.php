<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Log;
use STS\Models\ManualIdentityValidation;
use STS\Models\User;
use Tests\TestCase;

class ManualValidationPaymentControllerTest extends TestCase
{
    private const FRONTEND_BASE = 'https://app.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mercadopago.oauth_frontend_redirect' => self::FRONTEND_BASE]);
    }

    /** @return array<string, string> */
    private function redirectQueryParams(string $location): array
    {
        $parts = parse_url($location) ?: [];
        parse_str($parts['query'] ?? '', $query);

        return $query;
    }

    public function test_without_request_id_redirects_to_manual_validation_route(): void
    {
        $response = $this->get('api/mercadopago/manual-validation-success');

        $response->assertRedirect(self::FRONTEND_BASE.'/setting/identity-validation/manual');
    }

    public function test_frontend_base_trailing_slash_is_stripped_before_path_concat(): void
    {
        config(['services.mercadopago.oauth_frontend_redirect' => self::FRONTEND_BASE.'/']);

        $response = $this->get('api/mercadopago/manual-validation-success');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('//setting', $location);
        $response->assertRedirect(self::FRONTEND_BASE.'/setting/identity-validation/manual');
    }

    public function test_success_marks_validation_paid_stores_payment_id_and_redirects_with_success_flag(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $response = $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id.'&payment_id=mp-555'
        );

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(self::FRONTEND_BASE.'/setting/identity-validation/manual', $location);

        $query = $this->redirectQueryParams($location);
        $this->assertSame((string) $row->id, $query['request_id']);
        $this->assertSame('1', $query['payment_success']);

        $fresh = $row->fresh();
        $this->assertTrue((bool) $fresh->paid);
        $this->assertNotNull($fresh->paid_at);
        $this->assertSame('mp-555', $fresh->payment_id);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($row, $user): bool {
            return $message === 'Manual identity validation payment success'
                && (string) $context['request_id'] === (string) $row->id
                && (int) $context['user_id'] === (int) $user->id
                && (string) $context['payment_id'] === 'mp-555';
        });
    }

    public function test_success_uses_collection_id_when_payment_id_is_absent(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id.'&collection_id=col-999'
        )->assertRedirect();

        $this->assertSame('col-999', $row->fresh()->payment_id);
    }

    public function test_success_prefers_payment_id_when_both_payment_id_and_collection_id_are_present(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id
                .'&payment_id=primary&collection_id=ignored'
        )->assertRedirect();

        $this->assertSame('primary', $row->fresh()->payment_id);
    }

    public function test_non_success_result_does_not_mark_paid_and_redirects_with_encoded_result(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $response = $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id.'&result=failure'
        );

        $response->assertRedirect();
        $query = $this->redirectQueryParams((string) $response->headers->get('Location'));
        $this->assertSame('failure', $query['payment_result']);
        $this->assertArrayNotHasKey('payment_success', $query);

        $fresh = $row->fresh();
        $this->assertFalse((bool) $fresh->paid);
        $this->assertNull($fresh->paid_at);
    }

    public function test_non_success_result_is_urlencoded_in_redirect_query(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
        ]);

        $rawResult = 'declined by bank/issuer';
        $response = $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id.'&result='.urlencode($rawResult)
        );

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('payment_result='.urlencode($rawResult), $location);
        $this->assertStringNotContainsString('payment_success=1', $location);
    }

    public function test_unknown_request_id_does_not_persist_changes_but_redirect_includes_request_id(): void
    {
        $missingId = (int) (ManualIdentityValidation::query()->max('id') ?? 0) + 50_000;

        $response = $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$missingId.'&payment_id=x'
        );

        $response->assertRedirect();
        $query = $this->redirectQueryParams((string) $response->headers->get('Location'));
        $this->assertSame((string) $missingId, $query['request_id']);
        $this->assertSame('1', $query['payment_success']);

        $this->assertDatabaseMissing('manual_identity_validations', ['id' => $missingId]);
    }

    public function test_success_without_payment_identifiers_leaves_payment_id_null(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'payment_id' => null,
        ]);

        $this->get(
            'api/mercadopago/manual-validation-success?request_id='.$row->id
        )->assertRedirect();

        $fresh = $row->fresh();
        $this->assertTrue((bool) $fresh->paid);
        $this->assertNull($fresh->payment_id);

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($row, $user): bool {
            return $message === 'Manual identity validation payment success'
                && (string) $context['request_id'] === (string) $row->id
                && (int) $context['user_id'] === (int) $user->id
                && array_key_exists('payment_id', $context)
                && $context['payment_id'] === null;
        });
    }

    public function test_success_without_new_payment_identifier_logs_existing_payment_id_context(): void
    {
        Log::spy();

        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $row = ManualIdentityValidation::query()->create([
            'user_id' => $user->id,
            'paid' => false,
            'review_status' => ManualIdentityValidation::REVIEW_STATUS_PENDING,
            'payment_id' => 'previous-mp-id',
        ]);

        $this->get('api/mercadopago/manual-validation-success?request_id='.$row->id)->assertRedirect();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($row, $user): bool {
            return $message === 'Manual identity validation payment success'
                && (string) $context['request_id'] === (string) $row->id
                && (int) $context['user_id'] === (int) $user->id
                && (string) $context['payment_id'] === 'previous-mp-id';
        });
    }
}
