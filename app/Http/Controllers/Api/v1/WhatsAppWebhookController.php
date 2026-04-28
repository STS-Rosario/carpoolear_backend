<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Log;
use STS\Http\Controllers\Controller;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle WhatsApp webhook requests
     * Supports both verification requests and event notifications
     */
    public function handle(Request $request)
    {
        Log::info('WhatsApp webhook received', [
            'method' => $request->method(),
            'data' => $request->all(),
            'content' => $request->getContent(),
            'headers' => $request->headers->all(),
            'content_type' => $request->header('Content-Type'),
        ]);

        // Handle webhook verification request
        if ($request->method() === 'GET') {
            return $this->handleVerification($request);
        }

        // Handle webhook event notifications
        if ($request->method() === 'POST') {
            return $this->handleEventNotification($request);
        }

        // Return 405 Method Not Allowed for other HTTP methods
        return response()->json(['error' => 'Method not allowed'], 405);
    }

    /**
     * Handle webhook verification request from WhatsApp
     * WhatsApp sends a GET request with hub.mode, hub.verify_token, and hub.challenge parameters
     */
    private function handleVerification(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('WhatsApp webhook verification request', [
            'mode' => $mode,
            'token' => $token,
            'challenge' => $challenge,
        ]);

        // Verify the token matches your configured verify token
        $expectedToken = config('services.whatsapp.verify_token', 'your_verify_token_here');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('WhatsApp webhook verification successful');

            // Return the challenge string to complete verification
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'expected_token' => $expectedToken,
            'received_token' => $token,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle webhook event notifications from WhatsApp
     * WhatsApp sends POST requests with message events and status updates
     */
    private function handleEventNotification(Request $request)
    {
        $payload = $request->all();

        Log::info('WhatsApp webhook event notification', [
            'payload' => $payload,
        ]);

        // Verify the request is from WhatsApp (optional but recommended)
        if (! $this->verifyWebhookSignature($request)) {
            Log::warning('WhatsApp webhook signature verification failed');

            return response('Unauthorized', 401);
        }

        // Process the webhook payload
        try {
            $this->processWebhookPayload($payload);

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            // Still return 200 to prevent retries for processing errors
            return response()->json(['success' => false, 'error' => 'Processing failed'], 200);
        }
    }

    /**
     * Verify webhook signature (optional but recommended for security)
     */
    private function verifyWebhookSignature(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            Log::warning('No WhatsApp webhook signature found');

            return false;
        }

        $appSecret = config('services.whatsapp.app_secret');
        if (! $appSecret) {
            Log::warning('WhatsApp app secret not configured');

            return true; // Skip verification if not configured
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process the webhook payload based on the event type
     */
    private function processWebhookPayload(array $payload)
    {
        // Check if this is a WhatsApp Business Account webhook
        if (! isset($payload['object']) || $payload['object'] !== 'whatsapp_business_account') {
            Log::warning('Invalid webhook payload object', ['payload' => $payload]);

            return;
        }

        // Process each entry in the webhook
        foreach ($payload['entry'] ?? [] as $entry) {
            $this->processEntry($entry);
        }
    }

    /**
     * Process a single webhook entry
     */
    private function processEntry(array $entry)
    {
        $businessAccountId = $entry['id'] ?? null;

        foreach ($entry['changes'] ?? [] as $change) {
            $this->processChange($businessAccountId, $change);
        }
    }

    /**
     * Process a single change in the webhook
     */
    private function processChange($businessAccountId, array $change)
    {
        $field = $change['field'] ?? null;
        $value = $change['value'] ?? [];

        Log::info('Processing WhatsApp webhook change', [
            'business_account_id' => $businessAccountId,
            'field' => $field,
            'value' => $value,
        ]);

        switch ($field) {
            case 'messages':
                $this->handleMessages($businessAccountId, $value);
                break;
            case 'message_status':
                $this->handleMessageStatus($businessAccountId, $value);
                break;
            default:
                Log::info('Unhandled webhook field', ['field' => $field]);
                break;
        }
    }

    /**
     * Handle incoming messages
     */
    private function handleMessages($businessAccountId, array $value)
    {
        $messages = $value['messages'] ?? [];

        foreach ($messages as $message) {
            Log::info('Received WhatsApp message', [
                'business_account_id' => $businessAccountId,
                'message' => $message,
            ]);

            // TODO: Implement your message handling logic here
            // This could include:
            // - Storing messages in your database
            // - Processing commands or queries
            // - Sending automated responses
            // - Triggering notifications
        }
    }

    /**
     * Handle message status updates
     */
    private function handleMessageStatus($businessAccountId, array $value)
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            Log::info('WhatsApp message status update', [
                'business_account_id' => $businessAccountId,
                'status' => $status,
            ]);

            // TODO: Implement your status handling logic here
            // This could include:
            // - Updating message status in your database
            // - Triggering follow-up actions based on status
            // - Analytics and reporting
        }
    }
}
