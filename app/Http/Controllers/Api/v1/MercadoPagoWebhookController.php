<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\Trip;
use STS\Models\Payment;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\ConversationsManager;
use MercadoPago\SDK;
use MercadoPago\Payment as MPPayment;
use Log;

class MercadoPagoWebhookController extends Controller
{
    protected $tripLogic;
    protected $conversationManager;

    public function __construct(TripsManager $tripLogic, ConversationsManager $conversationManager)
    {
        $this->tripLogic = $tripLogic;
        $this->conversationManager = $conversationManager;
        
        // Initialize Mercado Pago SDK
        SDK::setAccessToken(config('services.mercadopago.access_token'));
    }

    public function handle(Request $request)
    {
        Log::info('MercadoPago webhook received', ['data' => $request->all()]);

        // Verify the request is from Mercado Pago
        if (!$this->verifyMercadoPagoRequest($request)) {
            Log::error('Invalid MercadoPago webhook request');
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $paymentId = $request->input('data.id');
        if (!$paymentId) {
            Log::error('No payment ID in webhook request');
            return response()->json(['error' => 'No payment ID'], 400);
        }

        // Find the payment in our database
        $payment = Payment::where('payment_id', $paymentId)->first();
        if (!$payment) {
            Log::error('Payment not found', ['payment_id' => $paymentId]);
            return response()->json(['error' => 'Payment not found'], 404);
        }

        // Get the payment status from Mercado Pago
        $mpPayment = $this->getMercadoPagoPayment($paymentId);
        if (!$mpPayment) {
            Log::error('Could not fetch payment from MercadoPago', ['payment_id' => $paymentId]);
            return response()->json(['error' => 'Could not fetch payment'], 500);
        }

        // Update payment status
        $this->updatePaymentStatus($payment, $mpPayment);

        return response()->json(['status' => 'success']);
    }

    protected function verifyMercadoPagoRequest(Request $request)
    {
        // Get required headers and parameters
        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');
        $dataId = $request->query('data.id');

        if (!$xSignature || !$xRequestId || !$dataId) {
            Log::error('Missing required headers or parameters in webhook request', [
                'has_signature' => !empty($xSignature),
                'has_request_id' => !empty($xRequestId),
                'has_data_id' => !empty($dataId)
            ]);
            return false;
        }

        // Parse x-signature to get ts and v1 values
        $parts = explode(',', $xSignature);
        $ts = null;
        $hash = null;

        foreach ($parts as $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) == 2) {
                $key = trim($keyValue[0]);
                $value = trim($keyValue[1]);
                if ($key === "ts") {
                    $ts = $value;
                } elseif ($key === "v1") {
                    $hash = $value;
                }
            }
        }

        if (!$ts || !$hash) {
            Log::error('Invalid x-signature format', ['signature' => $xSignature]);
            return false;
        }

        // Validate timestamp (allow 5 minutes tolerance)
        $timestamp = (int)($ts / 1000); // Convert milliseconds to seconds
        $now = time();
        if (abs($now - $timestamp) > 300) { // 5 minutes tolerance
            Log::error('Webhook timestamp is too old', [
                'timestamp' => $timestamp,
                'now' => $now,
                'difference' => abs($now - $timestamp)
            ]);
            return false;
        }

        // Get the secret key from config
        $secret = config('services.mercadopago.webhook_secret');
        if (!$secret) {
            Log::error('Mercado Pago webhook secret not configured');
            return false;
        }

        // Generate the manifest string
        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";

        // Generate HMAC signature
        $calculatedHash = hash_hmac('sha256', $manifest, $secret);

        // Compare signatures
        if (!hash_equals($calculatedHash, $hash)) {
            Log::error('Invalid webhook signature', [
                'calculated' => $calculatedHash,
                'received' => $hash
            ]);
            return false;
        }

        return true;
    }

    protected function getMercadoPagoPayment($paymentId)
    {
        try {
            // Use the SDK to fetch payment details
            $payment = MPPayment::find_by_id($paymentId);
            
            if (!$payment) {
                Log::error('Payment not found in MercadoPago', ['payment_id' => $paymentId]);
                return null;
            }

            return [
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'amount' => $payment->transaction_amount,
                'currency_id' => $payment->currency_id,
                'payment_method_id' => $payment->payment_method_id,
                'payment_type_id' => $payment->payment_type_id,
                'external_reference' => $payment->external_reference,
                'description' => $payment->description,
                'date_created' => $payment->date_created,
                'date_approved' => $payment->date_approved,
                'date_last_updated' => $payment->date_last_updated,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching MercadoPago payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function updatePaymentStatus(Payment $payment, $mpPayment)
    {
        $oldStatus = $payment->payment_status;
        $newStatus = $this->mapMercadoPagoStatus($mpPayment['status']);

        // TODO: check if pending and update trip state to pending_payment?
        // TODO: check if approved and update trip state to ready
        // TODO: check if failed and update trip state to payment_failed
        
        if ($oldStatus === $newStatus) {
            return;
        }

        $payment->payment_status = $newStatus;
        $payment->payment_data = array_merge($payment->payment_data ?? [], [
            'mp_status' => $mpPayment['status'],
            'mp_status_detail' => $mpPayment['status_detail'],
            'mp_payment_method' => $mpPayment['payment_method_id'],
            'mp_payment_type' => $mpPayment['payment_type_id'],
            'mp_currency' => $mpPayment['currency_id'],
            'mp_amount' => $mpPayment['amount'],
            'mp_date_created' => $mpPayment['date_created'],
            'mp_date_approved' => $mpPayment['date_approved'],
            'mp_date_updated' => $mpPayment['date_last_updated'],
            'last_webhook' => now()->toIso8601String()
        ]);

        if ($newStatus === Payment::STATUS_COMPLETED) {
            $payment->paid_at = now();
            $payment->trip->setStateReady()->save();
        } elseif ($newStatus === Payment::STATUS_FAILED) {
            $payment->trip->setStatePaymentFailed()->save();
        } elseif ($newStatus === Payment::STATUS_PENDING) {
            $payment->trip->setStatePendingPayment()->save();
        }

        $payment->save();

        Log::info('Payment status updated', [
            'payment_id' => $payment->payment_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'trip_id' => $payment->trip_id
        ]);
    }

    protected function mapMercadoPagoStatus($mpStatus)
    {
        $statusMap = [
            'approved' => Payment::STATUS_COMPLETED,
            'rejected' => Payment::STATUS_FAILED,
            'pending' => Payment::STATUS_PENDING,
            'in_process' => Payment::STATUS_PENDING,
            'cancelled' => Payment::STATUS_FAILED,
            'refunded' => Payment::STATUS_FAILED,
            'charged_back' => Payment::STATUS_FAILED
        ];

        return $statusMap[$mpStatus] ?? Payment::STATUS_PENDING;
    }
} 