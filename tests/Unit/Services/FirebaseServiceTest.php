<?php

namespace Tests\Unit\Services;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;
use STS\Services\FirebaseService;
use Tests\TestCase;

final class FirebaseServiceHarness extends FirebaseService
{
    public function __construct(
        private readonly HttpClient $guzzle,
        private readonly array $tokenPayload,
        string $firebaseProjectName,
    ) {
        Config::set('firebase.firebase_path', '');
        Config::set('firebase.firebase_project_name', $firebaseProjectName);
        parent::__construct();
        $ref = new ReflectionClass(FirebaseService::class);
        $p = $ref->getProperty('firebaseName');
        $p->setAccessible(true);
        $p->setValue($this, $firebaseProjectName);
    }

    protected function fetchMessagingAccessToken(): array
    {
        return $this->tokenPayload;
    }

    protected function httpClient(): HttpClient
    {
        return $this->guzzle;
    }
}

class FirebaseServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_access_token_returns_string_from_assertion_payload(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $service = new FirebaseServiceHarness($http, ['access_token' => 'unit-bearer'], 'proj-x');

        $this->assertSame('unit-bearer', $service->getAccessToken());
    }

    public function test_send_notification_android_posts_fcm_v1_url_with_bearer_and_template_payload(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->withArgs(function (string $url, array $options): bool {
                if (! str_contains($url, 'https://fcm.googleapis.com/v1/projects/myproj/messages:send')) {
                    return false;
                }
                if (($options['headers']['Authorization'] ?? '') !== 'Bearer unit-bearer') {
                    return false;
                }
                if (($options['headers']['Content-Type'] ?? '') !== 'application/json') {
                    return false;
                }
                $msg = $options['json']['message'] ?? [];
                if (($msg['token'] ?? null) !== 'device-1') {
                    return false;
                }
                $android = $msg['android'] ?? [];
                if (($android['notification']['title'] ?? null) !== 'Hello') {
                    return false;
                }
                if (($android['notification']['body'] ?? null) !== 'World') {
                    return false;
                }
                $data = $android['data'] ?? [];
                if (($data['plain'] ?? null) !== '1') {
                    return false;
                }
                if (($data['nested'] ?? null) !== '{"a":true}') {
                    return false;
                }

                return true;
            })
            ->andReturn(new Response(200, [], '{"name":"projects\/myproj\/messages\/abc"}'));

        $service = new FirebaseServiceHarness($http, ['access_token' => 'unit-bearer'], 'myproj');

        $body = $service->sendNotification(
            'device-1',
            ['title' => 'Hello', 'body' => 'World'],
            ['plain' => 1, 'nested' => ['a' => true]],
            'android'
        );

        $this->assertIsArray($body);
        $this->assertSame('projects/myproj/messages/abc', $body['name'] ?? null);
    }

    public function test_send_notification_ios_includes_apns_alert_headers_and_parallel_data_map(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->withArgs(function (string $url, array $options): bool {
                $msg = $options['json']['message'] ?? [];
                $apns = $msg['apns'] ?? [];
                $alert = $apns['payload']['aps']['alert'] ?? [];
                if (($alert['title'] ?? null) !== 'T' || ($alert['body'] ?? null) !== 'B') {
                    return false;
                }
                if (($apns['payload']['aps']['sound'] ?? null) !== 'default') {
                    return false;
                }
                if (($apns['payload']['aps']['badge'] ?? null) !== 1) {
                    return false;
                }
                if (($apns['headers']['apns-priority'] ?? null) !== '10') {
                    return false;
                }
                if (($msg['data']['flag'] ?? null) !== 'yes') {
                    return false;
                }

                return str_contains($url, '/projects/myproj/messages:send');
            })
            ->andReturn(new Response(200, [], '{"name":"n"}'));

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');
        $body = $service->sendNotification('dt', ['title' => 'T', 'body' => 'B'], ['flag' => 'yes'], 'IOS');

        $this->assertIsArray($body);
        $this->assertSame('n', $body['name'] ?? null);
    }

    public function test_send_notification_unknown_device_type_uses_webpush_branch(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->withArgs(function (string $url, array $options): bool {
                $msg = $options['json']['message'] ?? [];
                $wp = $msg['webpush'] ?? [];

                return ($wp['notification']['title'] ?? null) === 'Nt'
                    && ($wp['data']['x'] ?? null) === 'y';
            })
            ->andReturn(new Response(200, [], '{}'));

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');
        $body = $service->sendNotification('dt', ['title' => 'Nt', 'body' => 'Nb'], ['x' => 'y'], 'kiosk');

        $this->assertIsArray($body);
        $this->assertSame([], $body);
    }

    public function test_send_notification_client_exception_logs_structured_context_and_rethrows(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $response = new Response(400, [], json_encode([
            'error' => [
                'code' => 400,
                'message' => 'Invalid JSON',
                'status' => 'INVALID_ARGUMENT',
            ],
        ]));
        $exception = new ClientException('bad request', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                if (! str_starts_with($message, 'FirebaseService: FCM ClientException (HTTP 400)')) {
                    return false;
                }
                if (($context['device_token'] ?? null) !== 'tok') {
                    return false;
                }
                if (($context['device_type'] ?? null) !== 'android') {
                    return false;
                }
                if (($context['url'] ?? '') !== 'https://fcm.googleapis.com/v1/projects/myproj/messages:send') {
                    return false;
                }
                if (($context['project_name'] ?? null) !== 'myproj') {
                    return false;
                }
                if (($context['fcm_error_message'] ?? null) !== 'Invalid JSON') {
                    return false;
                }
                if (($context['request_payload']['message']['token'] ?? null) !== 'tok') {
                    return false;
                }

                return true;
            });

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');

        $this->expectException(ClientException::class);
        $service->sendNotification('tok', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_request_exception_logs_and_rethrows(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/p/messages:send');
        $response = new Response(503, [], 'unavailable');
        $exception = new RequestException('down', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_starts_with($message, 'FirebaseService: FCM RequestException')
                    && ($context['status_code'] ?? null) === 503
                    && ($context['device_token'] ?? null) === 'tok2';
            });

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $this->expectException(RequestException::class);
        $service->sendNotification('tok2', ['title' => 'a', 'body' => 'b'], [], 'android');
    }
}
