<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Log;
use STS\Models\User;
use STS\Services\IdentityVerificationOutcome;
use Tests\TestCase;

class IdentityVerificationClientEventApiTest extends TestCase
{
    public function test_authenticated_user_can_record_whitelisted_client_event(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');
        Log::spy();

        $this->postJson('api/users/identity-verification-events', [
            'name' => IdentityVerificationOutcome::NAME_CONFIRM_MODAL_CANCELLED,
            'surface' => 'choice_cards',
            'platform' => 'web',
            'app_version' => '4.1.0',
        ])->assertCreated();

        $this->assertDatabaseHas('identity_verification_events', [
            'user_id' => $user->id,
            'method' => 'mercado_pago',
            'name' => 'confirm_modal_cancelled',
            'surface' => 'choice_cards',
            'platform' => 'web',
            'app_version' => '4.1.0',
        ]);
    }

    public function test_rejects_non_whitelisted_client_event_name(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->postJson('api/users/identity-verification-events', [
            'name' => 'succeeded',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('identity_verification_events', [
            'user_id' => $user->id,
            'name' => 'succeeded',
        ]);
    }
}
