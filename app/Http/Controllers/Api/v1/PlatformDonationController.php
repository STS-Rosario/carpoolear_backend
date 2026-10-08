<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\User;
use STS\Services\PlatformDonationService;

class PlatformDonationController extends Controller
{
    public function __construct(private PlatformDonationService $platformDonationService)
    {
        $this->middleware('logged.optional')->only(['checkoutOnce', 'checkoutMonthly', 'checkoutQrOrder', 'paymentStatus']);
        $this->middleware('logged')->only(['myDonations']);
    }

    public function checkoutOnce(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $validated = $this->validateCheckoutRequest($request);
        $user = $this->resolveCheckoutUser($request, $validated);
        $result = $this->platformDonationService->checkoutOnce($user, $validated);

        return response()->json($result);
    }

    public function checkoutQrOrder(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $this->ensureQrEnabled();
        $validated = $this->validateCheckoutRequest($request);
        $user = $this->resolveCheckoutUser($request, $validated);
        $result = $this->platformDonationService->checkoutOnceQr($user, $validated);

        return response()->json($result);
    }

    public function checkoutMonthly(Request $request): JsonResponse
    {
        $this->ensureEnabled();
        $validated = $this->validateCheckoutRequest($request);
        $user = $this->resolveCheckoutUser($request, $validated);
        if (! $user) {
            return response()->json(['error' => 'user_id or authentication is required'], 422);
        }

        $result = $this->platformDonationService->checkoutMonthly($user, $validated);

        return response()->json($result);
    }

    public function paymentStatus(int $paymentId): JsonResponse
    {
        $payment = DonationPayment::query()->find($paymentId);
        if (! $payment) {
            abort(404);
        }

        return response()->json([
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }

    public function myDonations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $payments = DonationPayment::query()
            ->with('tier')
            ->where('user_id', $userId)
            ->latest()
            ->limit(50)
            ->get();

        $subscription = DonationSubscription::query()
            ->with('tier')
            ->where('user_id', $userId)
            ->latest()
            ->first();

        return response()->json([
            'payments' => $payments,
            'subscription' => $subscription,
        ]);
    }

    private function ensureEnabled(): void
    {
        if (! $this->platformDonationService->isEnabled()) {
            abort(503, 'Platform donations API is disabled');
        }
    }

    private function ensureQrEnabled(): void
    {
        if (! $this->platformDonationService->isQrEnabled()) {
            abort(503, 'QR payment is not available');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCheckoutRequest(Request $request): array
    {
        $validated = $request->validate([
            'tier_id' => 'nullable|integer|exists:donation_tiers,id',
            'amount' => 'nullable|integer|min:1',
            'source' => 'nullable|string|max:64',
            'trip_id' => 'nullable|integer',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (empty($validated['tier_id']) && empty($validated['amount'])) {
            abort(response()->json(['error' => 'tier_id or amount is required'], 422));
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveCheckoutUser(Request $request, array $validated): ?User
    {
        $authenticated = $request->user();
        if ($authenticated) {
            return $authenticated;
        }

        $guestUserId = $validated['user_id'] ?? $request->query('u') ?? $request->query('user');
        if (! $guestUserId) {
            return null;
        }

        return User::query()->find((int) $guestUserId);
    }
}
