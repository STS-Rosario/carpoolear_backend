<?php

namespace STS\Http\Controllers\Api\v1;

use STS\Http\Controllers\Controller;
use STS\Models\Campaign;
use STS\Models\CampaignReward;
use STS\Models\CampaignDonation;
use STS\Services\MercadoPagoService;
use Illuminate\Http\Request;

class CampaignRewardController extends Controller
{
    protected $mercadoPagoService;

    public function __construct(MercadoPagoService $mercadoPagoService)
    {
        $this->mercadoPagoService = $mercadoPagoService;
    }

    public function purchase(Request $request, Campaign $campaign, CampaignReward $reward)
    {
        if ($reward->campaign_id !== $campaign->id) {
            return response()->json(['error' => 'Reward does not belong to this campaign'], 404);
        }

        if (!$reward->is_active) {
            return response()->json(['error' => 'This reward is not available'], 400);
        }

        if ($reward->is_sold_out) {
            return response()->json(['error' => 'This reward is sold out'], 400);
        }

        try {
            // Create a CampaignDonation record first
            $donation = CampaignDonation::create([
                'campaign_id' => $campaign->id,
                'campaign_reward_id' => $reward->id,
                'amount_cents' => $reward->donation_amount_cents,
                'name' => $request->input('name'),
                'comment' => $request->input('comment'),
                'user_id' => $request->user()?->id,
                'status' => 'pending'
            ]);

            // Create the payment preference with the donation ID
            $preference = $this->mercadoPagoService->createPaymentPreferenceForCampaignDonation(
                $campaign->id,
                $reward->donation_amount_cents,
                $request->user()?->id,
                $reward->id,
                $donation->id
            );

            \Log::info('Preference created', ['preference' => $preference]);

            // Update the donation with the payment ID from MercadoPago
            $donation->update([
                'payment_id' => $preference->id
            ]);

            return response()->json([
                'message' => 'Payment preference created',
                'data' => [
                    'url' => $preference->init_point,
                    'sandbox_url' => $preference->sandbox_init_point
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating payment preference for campaign reward', [
                'campaign_id' => $campaign->id,
                'reward_id' => $reward->id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Could not create payment preference'], 500);
        }
    }
} 