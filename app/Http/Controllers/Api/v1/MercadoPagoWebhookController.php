<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\Trip;
use STS\Models\PaymentAttempt;
use STS\Services\Logic\TripsManager;
use STS\Services\Logic\ConversationsManager;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use Log;

class MercadoPagoWebhookController extends Controller
{
    protected $tripLogic;
    protected $conversationManager;
    protected $paymentClient;

    public function __construct(TripsManager $tripLogic, ConversationsManager $conversationManager)
    {
        $this->tripLogic = $tripLogic;
        $this->conversationManager = $conversationManager;
        
        // Initialize Mercado Pago SDK
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
        $this->paymentClient = new PaymentClient();
    }

    public function handle(Request $request)
    {
        Log::info('MercadoPago webhook received', [
            'method' => $request->method(),
            'type' => $request->header('type'),
            'action' => $request->header('action'),
            'data' => $request->all(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all(),
            'content_type' => $request->header('Content-Type')
        ]);

        // we only want action = payment.created
        \Log::info('MercadoPago webhook received', ['action' => $request->input('action')]);
        if ($request->input('action') !== 'payment.created') {
            // discard other actions
            return response()->json(['status' => 'success']);
        }
        // Verify the request is from Mercado Pago
        if (!$this->verifyMercadoPagoRequest($request)) {
            Log::error('Invalid MercadoPago webhook request');
            return response()->json(['error' => 'Invalid request'], 400);
        }
        Log::info('MercadoPago webhook request verified');

        $paymentId = $request->input('data_id');
        if (!$paymentId) {
            Log::error('No payment ID in webhook request');
            return response()->json(['error' => 'No payment ID'], 400);
        }
        \Log::info('MercadoPago $paymentId', ['payment_id' => $paymentId]);

        // Get the payment status from Mercado Pago
        $mpPayment = $this->getMercadoPagoPayment($paymentId);
        if (!$mpPayment) {
            Log::error('Could not fetch payment from MercadoPago', ['payment_id' => $paymentId]);
            return response()->json(['error' => 'Could not fetch payment'], 500);
        }
        Log::info('MP WEBHOOK payment', ['payment' => $mpPayment]);

        // parse tripId from external reference
        $externalReference = $mpPayment['external_reference'];
        $tripId = explode('Sellado de Viaje ID: ', $externalReference)[1];

        // get the trip for this payment
        $trip = Trip::where('id', $tripId)->first();
        if (!$trip) {
            Log::error('Trip not found', [
                'payment_id' => $paymentId,
                'external_reference' => $externalReference,
                'trip_id' => $tripId
            ]);
            return response()->json(['error' => 'Trip not found'], 404);
        }

        // create the payment attempt in the database
        $paymentAttempt = new PaymentAttempt();
        $paymentAttempt->payment_id = $paymentId;
        $paymentAttempt->payment_status = $this->mapMercadoPagoStatusToPaymentAttemptStatus($mpPayment['status']);
        $paymentAttempt->payment_data = $mpPayment;
        $paymentAttempt->trip_id = $trip->id;
        $paymentAttempt->user_id = $trip->user_id;
        $paymentAttempt->save();

        // Update payment status
        $newStatus = $this->updatePaymentStatus($paymentAttempt, $mpPayment);
        
        // Update trip status based on payment status
        $this->updateTripStatus($trip, $newStatus);

        return response()->json(['status' => 'success']);
    }

    protected function verifyMercadoPagoRequest(Request $request)
    {
        // Get required headers and parameters
        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');
        $dataId = $request->input('id');

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
            \Log::info('MP WEBHOOK fetching payment', ['payment_id' => $paymentId]);
            // Use the SDK client to fetch payment details
            $payment = $this->paymentClient->get($paymentId);
            
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
                'error' => $e->getMessage(),
                'api_response' => $e->getApiResponse() ? $e->getApiResponse()->getContent() : 'No API response'
            ]);
            return null;
        }
    }

    protected function updatePaymentStatus(PaymentAttempt $payment, $mpPayment)
    {
        $oldStatus = $payment->payment_status;
        $newStatus = $this->mapMercadoPagoStatusToPaymentAttemptStatus($mpPayment['status']);
        
        if ($oldStatus === $newStatus) {
            return $newStatus;
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

        if ($newStatus === PaymentAttempt::STATUS_COMPLETED) {
            $payment->paid_at = now();
        }

        $payment->save();

        Log::info('Payment status updated', [
            'payment_id' => $payment->payment_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);

        return $newStatus;
    }

    protected function updateTripStatus(Trip $trip, $paymentStatus)
    {
        if ($paymentStatus === PaymentAttempt::STATUS_COMPLETED) {
            $trip->setStateReady()->save();
        } elseif ($paymentStatus === PaymentAttempt::STATUS_FAILED) {
            $trip->setStatePaymentFailed()->save();
        } elseif ($paymentStatus === PaymentAttempt::STATUS_PENDING) {
            $trip->setStateAwaitingPayment()->save();
        }
    }

    protected function mapMercadoPagoStatusToPaymentAttemptStatus($mpStatus)
    {
        $statusMap = [
            'approved' => PaymentAttempt::STATUS_COMPLETED,
            'rejected' => PaymentAttempt::STATUS_FAILED,
            'pending' => PaymentAttempt::STATUS_PENDING,
            'in_process' => PaymentAttempt::STATUS_PENDING,
            'cancelled' => PaymentAttempt::STATUS_FAILED,
            'refunded' => PaymentAttempt::STATUS_FAILED,
            'in_mediation' => PaymentAttempt::STATUS_PENDING,
            'charged_back' => PaymentAttempt::STATUS_FAILED
        ];

        return $statusMap[$mpStatus] ?? PaymentAttempt::STATUS_PENDING;
    }

    protected function mapMercadoPagoStatusToTripState($mpStatus)
    {
        $statusMap = [
            'approved' => Trip::STATE_READY,
            'rejected' => Trip::STATE_PAYMENT_FAILED,
            'pending' => Trip::STATE_PENDING_PAYMENT,
            'in_process' => Trip::STATE_PENDING_PAYMENT,
            'cancelled' => Trip::STATE_PAYMENT_FAILED,
            'refunded' => Trip::STATE_PAYMENT_FAILED,
            'in_mediation' => Trip::STATE_PENDING_PAYMENT,
            'charged_back' => Trip::STATE_PAYMENT_FAILED
        ];

        return $statusMap[$mpStatus] ?? Trip::STATE_PENDING_PAYMENT;
    }
} 