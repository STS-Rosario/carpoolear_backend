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
        // Get the signature from the request header
        $signature = $request->header('x-signature');
        if (!$signature) {
            Log::error('No signature in webhook request');
            return false;
        }

        // Get the request body
        $payload = $request->getContent();
        
        // Verify the signature using Mercado Pago's public key
        $publicKey = config('services.mercadopago.public_key');
        if (!$publicKey) {
            Log::error('Mercado Pago public key not configured');
            return false;
        }

        // TODO: Implement proper signature verification
        // For now, we'll just check if the request has the expected structure
        return $request->has('data.id') && $request->has('type');
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