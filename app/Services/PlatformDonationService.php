<?php

namespace STS\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Jobs\UpdateDonationSubscriptionAmountsJob;
use STS\Models\DonationAmountAdjustment;
use STS\Models\DonationPayment;
use STS\Models\DonationSubscription;
use STS\Models\DonationSubscriptionCharge;
use STS\Models\DonationTier;
use STS\Models\User;

class PlatformDonationService
{
    public function __construct(private MercadoPagoService $mercadoPagoService) {}

    public function isEnabled(): bool
    {
        return (bool) config('carpoolear.platform_donations_api_enabled', false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveTiers(): array
    {
        return DonationTier::query()
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DonationTier $tier) => $this->formatTier($tier))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTier(DonationTier $tier): array
    {
        return [
            'id' => $tier->id,
            'slug' => $tier->slug,
            'label_key' => $tier->label_key,
            'amount' => (int) ($tier->amount_cents / 100),
            'amount_cents' => $tier->amount_cents,
            'icon' => $tier->icon,
            'is_active' => $tier->is_active,
            'sort_order' => $tier->sort_order,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{init_point: string, payment_id: int}
     */
    public function checkoutOnce(User $user, array $data): array
    {
        $tier = $this->resolveTier($data);
        $payment = DonationPayment::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'amount_cents' => $tier->amount_cents,
            'currency' => 'ARS',
            'status' => 'pending',
            'source' => $data['source'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
        ]);

        $preference = $this->mercadoPagoService->createPaymentPreferenceForPlatformDonation($payment);
        $payment->mp_preference_id = $preference->id ?? null;
        $payment->external_reference = $this->mercadoPagoService->createHashedExternalReferenceForPlatformDonation(
            $payment->id,
            'once',
            $user->id,
            $tier->slug
        );
        $payment->save();

        return [
            'init_point' => $preference->init_point,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{init_point: string, subscription_id: int}
     */
    public function checkoutMonthly(User $user, array $data): array
    {
        $tier = $this->resolveTier($data);
        if (empty($tier->mp_preapproval_plan_id)) {
            $plan = $this->mercadoPagoService->createPreapprovalPlan($tier);
            $tier->mp_preapproval_plan_id = $plan->id ?? null;
            $tier->save();
        }

        $subscription = DonationSubscription::create([
            'user_id' => $user->id,
            'donation_tier_id' => $tier->id,
            'mp_preapproval_plan_id' => $tier->mp_preapproval_plan_id,
            'status' => 'pending',
            'transaction_amount_cents' => $tier->amount_cents,
            'source' => $data['source'] ?? null,
            'trip_id' => $data['trip_id'] ?? null,
        ]);

        $subscription->external_reference = $this->mercadoPagoService->createHashedExternalReferenceForPlatformDonation(
            $subscription->id,
            'monthly',
            $user->id,
            $tier->slug
        );
        $subscription->save();

        $checkoutUrl = $this->mercadoPagoService->createPreapprovalCheckoutUrl($subscription);

        return [
            'init_point' => $checkoutUrl,
            'subscription_id' => $subscription->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    public function handlePlatformPayment(array $mpPayment): void
    {
        $decodedReference = $this->decodeExternalReference($mpPayment['external_reference'] ?? '');
        if ($decodedReference === null) {
            throw new \InvalidArgumentException('Invalid platform donation external reference');
        }

        if (! preg_match(
            '/Donación Plataforma ID: (\d+); Tipo: (\w+); User ID: ([^;]+); Tier: (\w+)/',
            $decodedReference,
            $matches
        )) {
            throw new \InvalidArgumentException('Invalid platform donation reference format');
        }

        $recordId = (int) $matches[1];
        $type = $matches[2];
        $userId = $matches[3] === 'Anonymous' ? null : (int) $matches[3];

        if ($type === 'once') {
            $this->applyOneTimePayment($recordId, $userId, $mpPayment);

            return;
        }

        if ($type === 'monthly') {
            $this->applySubscriptionCharge($recordId, $mpPayment);
        }
    }

    /**
     * @param  array<string, mixed>  $preapproval
     */
    public function handleSubscriptionPreapproval(array $preapproval): void
    {
        $subscription = $this->findSubscriptionFromPreapproval($preapproval);
        if (! $subscription) {
            return;
        }

        $mpStatus = strtolower((string) ($preapproval['status'] ?? ''));
        $subscription->mp_preapproval_id = $preapproval['id'] ?? $subscription->mp_preapproval_id;
        $subscription->status = $this->mapPreapprovalStatus($mpStatus);
        $subscription->transaction_amount_cents = $this->amountCentsFromMp(
            $preapproval['auto_recurring']['transaction_amount'] ?? ($subscription->transaction_amount_cents / 100)
        );

        if (! empty($preapproval['next_payment_date'])) {
            $subscription->next_payment_date = Carbon::parse($preapproval['next_payment_date'])->toDateString();
        }

        $subscription->save();

        if ($subscription->user_id) {
            $user = User::find($subscription->user_id);
            if ($user) {
                $user->monthly_donate = $subscription->status === 'authorized';
                $user->save();
            }
        }

        if ($subscription->status === 'authorized') {
            $this->writeLegacyDonationIntent($subscription->user_id, (float) ($subscription->transaction_amount_cents / 100), true);
        }

        if ($subscription->status === 'cancelled') {
            $this->writeLegacyDonationIntent($subscription->user_id, 0, false);
        }
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    public function handleSubscriptionAuthorizedPayment(array $mpPayment): void
    {
        $preapprovalId = $mpPayment['preapproval_id'] ?? ($mpPayment['metadata']['preapproval_id'] ?? null);
        $subscription = null;

        if ($preapprovalId) {
            $subscription = DonationSubscription::query()
                ->where('mp_preapproval_id', $preapprovalId)
                ->first();
        }

        if (! $subscription) {
            $externalReference = $mpPayment['external_reference'] ?? '';
            $decodedReference = $this->decodeExternalReference($externalReference);
            if ($decodedReference && preg_match('/Donación Plataforma ID: (\d+)/', $decodedReference, $matches)) {
                $subscription = DonationSubscription::find((int) $matches[1]);
            }
        }

        if (! $subscription) {
            return;
        }

        $this->upsertSubscriptionCharge($subscription, $mpPayment);
    }

    public function applyInflation(DonationTier $tier, int $newAmountCents, int $adminUserId): DonationAmountAdjustment
    {
        $oldAmountCents = $tier->amount_cents;
        $tier->amount_cents = $newAmountCents;
        $plan = $this->mercadoPagoService->createPreapprovalPlan($tier);

        $tier->mp_preapproval_plan_id = $plan->id ?? $tier->mp_preapproval_plan_id;
        $tier->effective_from = now();
        $tier->save();

        $adjustment = DonationAmountAdjustment::create([
            'donation_tier_id' => $tier->id,
            'old_amount_cents' => $oldAmountCents,
            'new_amount_cents' => $newAmountCents,
            'admin_user_id' => $adminUserId,
        ]);

        UpdateDonationSubscriptionAmountsJob::dispatch($adjustment->id);

        return $adjustment;
    }

    public function syncSubscriptionAmountsForAdjustment(DonationAmountAdjustment $adjustment): void
    {
        $tier = $adjustment->tier;
        $updated = 0;
        $failed = 0;

        DonationSubscription::query()
            ->where('donation_tier_id', $tier->id)
            ->where('status', 'authorized')
            ->whereNotNull('mp_preapproval_id')
            ->orderBy('id')
            ->chunkById(20, function ($subscriptions) use ($adjustment, &$updated, &$failed) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $this->mercadoPagoService->updatePreapprovalAmount(
                            $subscription->mp_preapproval_id,
                            $adjustment->new_amount_cents
                        );
                        $subscription->transaction_amount_cents = $adjustment->new_amount_cents;
                        $subscription->save();
                        $updated++;
                    } catch (\Throwable $e) {
                        \Log::error('Failed to update donation subscription amount', [
                            'subscription_id' => $subscription->id,
                            'mp_preapproval_id' => $subscription->mp_preapproval_id,
                            'message' => $e->getMessage(),
                        ]);
                        $failed++;
                    }
                }
            });

        $adjustment->subscriptions_updated = $updated;
        $adjustment->subscriptions_failed = $failed;
        $adjustment->applied_at = now();
        $adjustment->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $oneTimeTotal = (int) DonationPayment::query()->where('status', 'approved')->sum('amount_cents');
        $recurringTotal = (int) DonationSubscriptionCharge::query()->where('status', 'approved')->sum('amount_cents');
        $activeSubscriptions = DonationSubscription::query()->where('status', 'authorized')->count();
        $mrrCents = (int) DonationSubscription::query()
            ->where('status', 'authorized')
            ->sum('transaction_amount_cents');

        return [
            'total_donated_cents' => $oneTimeTotal + $recurringTotal,
            'one_time_total_cents' => $oneTimeTotal,
            'recurring_total_cents' => $recurringTotal,
            'active_subscriptions' => $activeSubscriptions,
            'estimated_mrr_cents' => $mrrCents,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTier(array $data): DonationTier
    {
        if (! empty($data['tier_id'])) {
            return DonationTier::query()->active()->findOrFail($data['tier_id']);
        }

        if (! empty($data['amount'])) {
            $amountCents = (int) $data['amount'] * 100;

            return DonationTier::query()
                ->active()
                ->where('amount_cents', $amountCents)
                ->firstOrFail();
        }

        throw new \InvalidArgumentException('tier_id or amount is required');
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    private function applyOneTimePayment(int $paymentId, ?int $userId, array $mpPayment): void
    {
        $payment = DonationPayment::find($paymentId);
        if (! $payment) {
            return;
        }

        $status = $this->mapPaymentStatus($mpPayment['status'] ?? 'pending');
        $payment->status = $status;
        $payment->mp_payment_id = (string) ($mpPayment['id'] ?? $payment->mp_payment_id);
        if ($status === 'approved') {
            $payment->paid_at = ! empty($mpPayment['date_approved'])
                ? Carbon::parse($mpPayment['date_approved'])
                : now();
        }
        $payment->save();

        if ($status === 'approved') {
            $this->writeLegacyDonationIntent($payment->user_id ?? $userId, (float) ($payment->amount_cents / 100), true);
        }
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    private function applySubscriptionCharge(int $subscriptionId, array $mpPayment): void
    {
        $subscription = DonationSubscription::find($subscriptionId);
        if (! $subscription) {
            return;
        }

        $this->upsertSubscriptionCharge($subscription, $mpPayment);
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    private function upsertSubscriptionCharge(DonationSubscription $subscription, array $mpPayment): void
    {
        $mpPaymentId = (string) ($mpPayment['id'] ?? '');
        if ($mpPaymentId === '') {
            return;
        }

        $status = $this->mapPaymentStatus($mpPayment['status'] ?? 'pending');
        $amountCents = $this->amountCentsFromMp(
            $mpPayment['transaction_amount'] ?? $mpPayment['amount'] ?? 0
        );

        DonationSubscriptionCharge::updateOrCreate(
            ['mp_payment_id' => $mpPaymentId],
            [
                'donation_subscription_id' => $subscription->id,
                'amount_cents' => $amountCents,
                'status' => $status,
                'charged_at' => ! empty($mpPayment['date_approved'])
                    ? Carbon::parse($mpPayment['date_approved'])
                    : now(),
            ]
        );

        if ($status === 'approved') {
            $subscription->last_charged_at = ! empty($mpPayment['date_approved'])
                ? Carbon::parse($mpPayment['date_approved'])
                : now();
            $subscription->save();
            $this->writeLegacyDonationIntent($subscription->user_id, (float) ($amountCents / 100), true);
        }
    }

    /**
     * @param  array<string, mixed>  $preapproval
     */
    private function findSubscriptionFromPreapproval(array $preapproval): ?DonationSubscription
    {
        $preapprovalId = $preapproval['id'] ?? null;
        if ($preapprovalId) {
            $byMpId = DonationSubscription::query()->where('mp_preapproval_id', $preapprovalId)->first();
            if ($byMpId) {
                return $byMpId;
            }
        }

        $externalReference = $preapproval['external_reference'] ?? '';
        $decodedReference = $this->decodeExternalReference($externalReference);
        if ($decodedReference && preg_match('/Donación Plataforma ID: (\d+)/', $decodedReference, $matches)) {
            return DonationSubscription::find((int) $matches[1]);
        }

        return null;
    }

    private function decodeExternalReference(string $externalReference): ?string
    {
        if ($externalReference === '') {
            return null;
        }

        if (strpos($externalReference, ':') === false) {
            return $externalReference;
        }

        $parts = explode(':', $externalReference, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $referenceString = base64_decode($parts[1]);
        $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
        $expectedHash = hash('sha256', $referenceString.$salt);

        if (! hash_equals($parts[0], $expectedHash)) {
            return null;
        }

        return $referenceString;
    }

    private function mapPaymentStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => 'approved',
            'refunded', 'charged_back' => 'refunded',
            'rejected', 'cancelled' => 'rejected',
            default => 'pending',
        };
    }

    private function mapPreapprovalStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'authorized' => 'authorized',
            'paused' => 'paused',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function amountCentsFromMp(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function writeLegacyDonationIntent(?int $userId, float $amount, bool $hasDonated): void
    {
        if (! $userId) {
            return;
        }

        DB::table('donations')->insert([
            'user_id' => $userId,
            'month' => now()->format('Y-m'),
            'has_donated' => $hasDonated ? 1 : 0,
            'has_denied' => 0,
            'ammount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
