<?php

namespace STS\Services;

use Facebook\Facebook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use STS\Services\HttpClients\DevCurlHttpClient;

class SmsService
{
    protected $provider;

    protected $config;

    public function __construct()
    {
        $this->provider = config('sms.default', 'whatsapp');
        $this->config = config('sms.providers.'.$this->provider, []);
    }

    /**
     * Send SMS message
     *
     * @param  string  $to
     * @param  string  $message
     * @return bool
     */
    public function send($to, $message)
    {
        try {
            switch ($this->provider) {
                case 'whatsapp':
                    return $this->sendViaWhatsApp($to, $message);
                case 'smsmasivos':
                    return $this->sendViaSmsMasivos($to, $message);
                case 'local':
                    return $this->sendViaLocal($to, $message);
                default:
                    Log::error('SMS provider not configured: '.$this->provider);

                    return false;
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send via WhatsApp using Facebook Graph API
     */
    protected function sendViaWhatsApp($to, $message)
    {
        $appId = $this->config['app_id'] ?? null;
        $appSecret = $this->config['app_secret'] ?? null;
        $accessToken = $this->config['access_token'] ?? null;
        $phoneNumberId = $this->config['phone_number_id'] ?? null;
        $graphVersion = $this->config['default_graph_version'] ?? 'v22.0';

        if (! $appId || ! $appSecret || ! $accessToken || ! $phoneNumberId) {
            Log::error('WhatsApp configuration missing');

            return false;
        }

        try {
            // Configure Facebook SDK based on environment
            $fbConfig = [
                'app_id' => $appId,
                'app_secret' => $appSecret,
                'default_graph_version' => $graphVersion,
            ];

            // Format phone number for WhatsApp (remove + and add country code if needed)
            $formattedPhone = $this->formatPhoneForWhatsApp($to);

            // Extract code and expiration from message
            $code = $this->extractCodeFromMessage($message);
            $expires = config('sms.verification.expires_in_minutes', 5);

            if (app()->environment('local', 'development')) {
                // In development, try to use Laravel HTTP client with SSL disabled
                try {
                    $apiUrl = "https://graph.facebook.com/{$graphVersion}/{$phoneNumberId}/messages";

                    Log::info('WhatsApp API URL being called', [
                        'url' => $apiUrl,
                        'phone_number_id' => $phoneNumberId,
                        'graph_version' => $graphVersion,
                    ]);

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer '.$accessToken,
                        'Content-Type' => 'application/json',
                    ])->withOptions([
                        'verify' => false,
                        'timeout' => 60,
                    ])->post($apiUrl, [
                        'messaging_product' => 'whatsapp',
                        'to' => $formattedPhone,
                        'type' => 'template',
                        'template' => [
                            'name' => 'verification_code',
                            'language' => ['code' => 'es_AR'],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $code],
                                        ['type' => 'text', 'text' => (string) $expires],
                                    ],
                                ],
                            ],
                        ],
                    ]);

                    if ($response->successful()) {
                        $body = $response->json();
                        if (isset($body['messages'][0]['id'])) {
                            Log::info('WhatsApp message sent successfully via Laravel HTTP client to: '.$formattedPhone.' with message: '.$message);

                            return true;
                        } else {
                            Log::error('WhatsApp API error via Laravel HTTP client: '.json_encode($body));

                            return false;
                        }
                    } else {
                        Log::error('WhatsApp HTTP client failed with status: '.$response->status().' - '.$response->body());

                        return false;
                    }
                } catch (\Exception $e) {
                    Log::error('Laravel HTTP client failed: '.$e->getMessage());
                    // Fall back to Facebook SDK
                    $fbConfig['http_client_handler'] = new DevCurlHttpClient;
                }
            } else {
                // In production, use default settings
                $fbConfig['http_client_handler'] = 'stream';
            }

            $fb = new Facebook($fbConfig);

            // Debug logging
            Log::info('WhatsApp API request details', [
                'to' => $to,
                'formatted_phone' => $formattedPhone,
                'message' => $message,
                'extracted_code' => $code,
                'expires' => $expires,
                'phone_number_id' => $phoneNumberId,
                'http_client_handler' => app()->environment('local', 'development') ? 'LaravelHttpClient' : 'stream',
                'environment' => app()->environment(),
                'curl_available' => function_exists('curl_version'),
                'curl_version' => function_exists('curl_version') ? curl_version() : null,
            ]);

            // Prepare template parameters
            $parameters = [
                ['type' => 'text', 'text' => $code],
                ['type' => 'text', 'text' => (string) $expires],
            ];

            $response = $fb->post(
                "/{$phoneNumberId}/messages",
                [
                    'messaging_product' => 'whatsapp',
                    'to' => $formattedPhone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'verification_code',
                        'language' => ['code' => 'es_AR'],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => $parameters,
                            ],
                        ],
                    ],
                ],
                $accessToken
            );

            $body = $response->getDecodedBody();

            if (isset($body['messages'][0]['id'])) {
                Log::info('WhatsApp message sent successfully to: '.$formattedPhone.' with message: '.$message);

                return true;
            } else {
                Log::error('WhatsApp API error: '.json_encode($body));

                return false;
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp sending failed', [
                'error' => $e->getMessage(),
                'to' => $to,
                'formatted_phone' => $formattedPhone ?? 'unknown',
                'message' => $message,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Extract verification code from message
     */
    protected function extractCodeFromMessage($message)
    {
        // Extract 6-digit code from message
        if (preg_match('/\b(\d{6})\b/', $message, $matches)) {
            return $matches[1];
        }

        // Fallback: generate a random code
        return $this->generateVerificationCode();
    }

    /**
     * Format phone number for WhatsApp
     */
    protected function formatPhoneForWhatsApp($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present (assuming Argentina +54)
        if (strlen($phone) === 10 && substr($phone, 0, 1) !== '0') {
            $phone = '54'.$phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = '54'.substr($phone, 1);
        }

        return $phone; // WhatsApp expects number without +
    }

    /**
     * Send via SMS Masivos
     */
    protected function sendViaSmsMasivos($to, $message)
    {
        $apiKey = $this->config['api_key'] ?? null;
        $testMode = $this->config['test_mode'] ?? false;

        if (! $apiKey) {
            Log::error('SMS Masivos API key missing');

            return false;
        }

        // Format phone number for SMS Masivos (Argentina: 10 digits without 0 and 15)
        $formattedPhone = $this->formatPhoneForSmsMasivos($to);

        // Prepare parameters
        $params = [
            'api' => 1,
            'apikey' => $apiKey,
            'tos' => $formattedPhone,
            'texto' => $message,
            'respuestanumerica' => 1, // Get numeric response codes
        ];

        // Add test mode if enabled
        if ($testMode) {
            $params['test'] = 1;
        }

        // Make the HTTP request
        $response = Http::get('http://servicio.smsmasivos.com.ar/enviar_sms.asp', $params);

        if ($response->successful()) {
            $responseText = trim($response->body());

            // Check for successful response
            if ($responseText === 'OK') {
                Log::info('SMS sent via SMS Masivos to: '.$formattedPhone.' with message: '.$message);

                return true;
            }

            // Check for test mode response
            if (strpos($responseText, 'probando sin enviar') !== false) {
                Log::info('SMS test mode via SMS Masivos to: '.$formattedPhone.' with message: '.$message);

                return true;
            }

            // Log error response
            Log::error('SMS Masivos error: '.$responseText);

            return false;
        }

        Log::error('SMS Masivos HTTP error: '.$response->status());

        return false;
    }

    /**
     * Format phone number for SMS Masivos (Argentina specific)
     */
    protected function formatPhoneForSmsMasivos($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // For Argentina: remove country code and leading zeros
        if (strlen($phone) === 13 && substr($phone, 0, 2) === '54') {
            // Remove country code (54)
            $phone = substr($phone, 2);
        }

        // Remove leading 0 if present
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = substr($phone, 1);
        }

        // Remove 15 prefix if present (mobile prefix in Argentina)
        if (strlen($phone) === 11 && substr($phone, 0, 2) === '15') {
            $phone = substr($phone, 2);
        }

        // Ensure we have exactly 10 digits for Argentina
        if (strlen($phone) !== 10) {
            Log::warning('Phone number format may be incorrect for SMS Masivos: '.$phone);
        }

        return $phone;
    }

    /**
     * Send via local provider (for testing/development)
     */
    protected function sendViaLocal($to, $message)
    {
        // For development/testing, just log the SMS
        Log::info('SMS would be sent to: '.$to.' with message: '.$message);

        // You can also log to a file for easier debugging
        $logMessage = date('Y-m-d H:i:s').' - SMS to '.$to.': '.$message.PHP_EOL;
        file_put_contents(storage_path('logs/sms.log'), $logMessage, FILE_APPEND | LOCK_EX);

        return true;
    }

    /**
     * Generate a random verification code
     */
    public function generateVerificationCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Format phone number for SMS sending
     */
    public function formatPhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present (assuming Argentina +54)
        if (strlen($phone) === 10 && substr($phone, 0, 1) !== '0') {
            $phone = '54'.$phone;
        } elseif (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            $phone = '54'.substr($phone, 1);
        }

        return '+'.$phone;
    }
}
