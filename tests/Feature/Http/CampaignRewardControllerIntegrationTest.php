<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use MercadoPago\Resources\Preference;
use Mockery;
use STS\Models\Campaign;
use STS\Models\CampaignDonation;
use STS\Models\CampaignReward;
use STS\Models\User;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class CampaignRewardControllerIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private function bearerTokenForUser(User $user): string
    {
        return $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => '123456',
        ])->assertOk()->json('token');
    }

    private function makeCampaign(): Campaign
    {
        return Campaign::create([
            'slug' => 'cmp-'.uniqid('', true),
            'title' => 'Campaign Title',
            'description' => 'Campaign description.',
            'image_path' => null,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'payment_slug' => 'pay-'.uniqid('', true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeReward(Campaign $campaign, array $overrides = []): CampaignReward
    {
        return CampaignReward::create(array_merge([
            'campaign_id' => $campaign->id,
            'title' => 'Reward',
            'description' => 'Reward description.',
            'donation_amount_cents' => 2500,
            'quantity_available' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function purchaseUrl(Campaign $campaign, CampaignReward $reward): string
    {
        return '/api/campaigns/'.$campaign->slug.'/rewards/'.$reward->id.'/purchase';
    }

    public function test_purchase_returns_not_found_when_reward_belongs_to_another_campaign(): void
    {
        $campaignA = $this->makeCampaign();
        $campaignB = $this->makeCampaign();
        $rewardOnB = $this->makeReward($campaignB);

        $this->mock(MercadoPagoService::class)->shouldNotReceive('createPaymentPreferenceForCampaignDonation');

        $this->postJson($this->purchaseUrl($campaignA, $rewardOnB))
            ->assertNotFound()
            ->assertExactJson(['error' => 'Reward does not belong to this campaign']);

        $this->assertSame(0, CampaignDonation::count());
    }

    public function test_purchase_returns_bad_request_when_reward_is_inactive(): void
    {
        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign, ['is_active' => false]);

        $this->mock(MercadoPagoService::class)->shouldNotReceive('createPaymentPreferenceForCampaignDonation');

        $this->postJson($this->purchaseUrl($campaign, $reward))
            ->assertStatus(400)
            ->assertExactJson(['error' => 'This reward is not available']);

        $this->assertSame(0, CampaignDonation::count());
    }

    public function test_purchase_returns_bad_request_when_reward_is_sold_out(): void
    {
        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign, ['quantity_available' => 1]);

        CampaignDonation::create([
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'payment_id' => null,
            'amount_cents' => $reward->donation_amount_cents,
            'name' => null,
            'comment' => null,
            'user_id' => null,
            'status' => 'paid',
        ]);

        $this->mock(MercadoPagoService::class)->shouldNotReceive('createPaymentPreferenceForCampaignDonation');

        $this->postJson($this->purchaseUrl($campaign, $reward))
            ->assertStatus(400)
            ->assertExactJson(['error' => 'This reward is sold out']);

        $this->assertSame(1, CampaignDonation::count());
    }

    public function test_purchase_creates_pending_donation_returns_urls_and_stores_preference_id(): void
    {
        Log::spy();

        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign);

        $preference = new Preference;
        $preference->id = 'pref-test-'.uniqid('', true);
        $preference->init_point = 'https://checkout.example/live';
        $preference->sandbox_init_point = 'https://checkout.example/sandbox';

        $this->mock(MercadoPagoService::class, function ($mock) use ($campaign, $reward, $preference) {
            $mock->shouldReceive('createPaymentPreferenceForCampaignDonation')
                ->once()
                ->with(
                    $campaign->id,
                    $reward->donation_amount_cents,
                    null,
                    $reward->id,
                    Mockery::type('int')
                )
                ->andReturn($preference);
        });

        $this->postJson($this->purchaseUrl($campaign, $reward), [
            'name' => 'Public Donor',
            'comment' => 'Thanks',
        ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Payment preference created',
                'data' => [
                    'url' => 'https://checkout.example/live',
                    'sandbox_url' => 'https://checkout.example/sandbox',
                ],
            ]);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Preference created'
                && isset($context['preference'])
                && $context['preference'] !== null;
        });

        $this->assertDatabaseHas('campaign_donations', [
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'amount_cents' => $reward->donation_amount_cents,
            'name' => 'Public Donor',
            'comment' => 'Thanks',
            'user_id' => null,
            'status' => 'pending',
            'payment_id' => (string) $preference->id,
        ]);
    }

    public function test_purchase_passes_authenticated_user_id_to_payment_service(): void
    {
        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign);
        $user = User::factory()->create();
        $token = $this->bearerTokenForUser($user);

        $preference = new Preference;
        $preference->id = 'pref-auth-'.uniqid('', true);
        $preference->init_point = 'https://checkout.example/live';
        $preference->sandbox_init_point = 'https://checkout.example/sandbox';

        $this->mock(MercadoPagoService::class, function ($mock) use ($campaign, $reward, $user, $preference) {
            $mock->shouldReceive('createPaymentPreferenceForCampaignDonation')
                ->once()
                ->with(
                    $campaign->id,
                    $reward->donation_amount_cents,
                    $user->id,
                    $reward->id,
                    Mockery::type('int')
                )
                ->andReturn($preference);
        });

        $this->postJson($this->purchaseUrl($campaign, $reward), [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk();

        $this->assertDatabaseHas('campaign_donations', [
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'user_id' => $user->id,
            'payment_id' => (string) $preference->id,
        ]);
    }

    public function test_purchase_returns_server_error_when_payment_preference_fails(): void
    {
        Log::spy();

        $campaign = $this->makeCampaign();
        $reward = $this->makeReward($campaign);

        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('createPaymentPreferenceForCampaignDonation')
                ->once()
                ->andThrow(new \RuntimeException('Mercado Pago unavailable'));
        });

        $this->postJson($this->purchaseUrl($campaign, $reward))
            ->assertStatus(500)
            ->assertExactJson(['error' => 'Could not create payment preference']);

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($campaign, $reward): bool {
            return str_contains($message, 'Error creating payment preference for campaign reward')
                && (int) $context['campaign_id'] === (int) $campaign->id
                && (int) $context['reward_id'] === (int) $reward->id
                && str_contains((string) $context['error'], 'Mercado Pago unavailable');
        });

        $this->assertDatabaseHas('campaign_donations', [
            'campaign_id' => $campaign->id,
            'campaign_reward_id' => $reward->id,
            'status' => 'pending',
            'payment_id' => null,
        ]);
    }
}
