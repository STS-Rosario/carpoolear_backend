<?php

namespace STS\Services;

use STS\Models\PaymentAttempt;
use STS\Models\Trip;

class SelladoPaymentApplier
{
    public function record(Trip $trip, array $mpPayment): void
    {
        $paymentAttempt = PaymentAttempt::query()->firstOrNew([
            'payment_id' => $mpPayment['id'],
        ]);
        $paymentAttempt->trip_id = $trip->id;
        $paymentAttempt->user_id = $trip->user_id;
        if (! $paymentAttempt->exists) {
            $paymentAttempt->payment_status = $this->mapStatus($mpPayment['status'] ?? null);
            $paymentAttempt->payment_data = $mpPayment;
        }
        $paymentAttempt->save();

        $newStatus = $this->updatePaymentStatus($paymentAttempt, $mpPayment);
        $this->updateTripStatus($trip, $newStatus);
    }

    public function tripIdFromExternalReference(?string $externalReference): ?int
    {
        if (! is_string($externalReference) || $externalReference === '') {
            return null;
        }

        if (preg_match('/^trip_sellado_(\d+)$/', $externalReference, $matches)) {
            return (int) $matches[1];
        }

        $decodedReference = $this->parseExternalReference($externalReference);
        if (! is_string($decodedReference) || stripos($decodedReference, 'Sellado de Viaje ID: ') === false) {
            return null;
        }

        $tripId = explode('Sellado de Viaje ID: ', $decodedReference)[1] ?? null;

        return is_numeric($tripId) ? (int) $tripId : null;
    }

    public function parseExternalReference($externalReference): ?string
    {
        if (strpos($externalReference, ':') !== false) {
            $parts = explode(':', $externalReference, 2);
            if (count($parts) !== 2) {
                return null;
            }

            $hash = $parts[0];
            $encodedData = $parts[1];
            $referenceString = base64_decode($encodedData);
            $salt = config('services.mercadopago.reference_salt', 'carpoolear_2024_secure_salt');
            $expectedHash = hash('sha256', $referenceString.$salt);

            if (! hash_equals($hash, $expectedHash)) {
                return null;
            }

            return $referenceString;
        }

        return $externalReference;
    }

    protected function updatePaymentStatus(PaymentAttempt $payment, array $mpPayment)
    {
        $oldStatus = $payment->payment_status;
        $newStatus = $this->mapStatus($mpPayment['status'] ?? null);

        if ($oldStatus === $newStatus) {
            return $newStatus;
        }

        $payment->payment_status = $newStatus;
        $payment->payment_data = array_merge($payment->payment_data ?? [], [
            'mp_status' => $mpPayment['status'] ?? null,
            'mp_status_detail' => $mpPayment['status_detail'] ?? null,
            'last_webhook' => now()->toIso8601String(),
        ]);

        if ($newStatus === PaymentAttempt::STATUS_COMPLETED) {
            $payment->paid_at = now();
        }

        $payment->save();

        return $newStatus;
    }

    protected function updateTripStatus(Trip $trip, $paymentStatus): void
    {
        if ($paymentStatus === PaymentAttempt::STATUS_COMPLETED) {
            $trip->setStateReady()->save();
            app(FriendTripAlertService::class)->notifyIfVisible($trip->fresh());
        } elseif ($paymentStatus === PaymentAttempt::STATUS_FAILED) {
            $trip->setStatePaymentFailed()->save();
        } elseif ($paymentStatus === PaymentAttempt::STATUS_PENDING) {
            $trip->setStateAwaitingPayment()->save();
        }
    }

    protected function mapStatus($mpStatus)
    {
        $statusMap = [
            'approved' => PaymentAttempt::STATUS_COMPLETED,
            'rejected' => PaymentAttempt::STATUS_FAILED,
            'pending' => PaymentAttempt::STATUS_PENDING,
            'in_process' => PaymentAttempt::STATUS_PENDING,
            'cancelled' => PaymentAttempt::STATUS_FAILED,
            'refunded' => PaymentAttempt::STATUS_FAILED,
            'in_mediation' => PaymentAttempt::STATUS_PENDING,
            'charged_back' => PaymentAttempt::STATUS_FAILED,
        ];

        return $statusMap[$mpStatus] ?? PaymentAttempt::STATUS_PENDING;
    }
}
