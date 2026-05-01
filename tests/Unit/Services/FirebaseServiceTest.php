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
use ReflectionMethod;
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

    public function test_send_notification_client_exception_logs_full_context_including_fcm_error_fields(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $payload = [
            'error' => [
                'code' => 400,
                'message' => 'Invalid JSON',
                'status' => 'INVALID_ARGUMENT',
                'details' => [['@type' => 'type.googleapis.com/google.rpc.BadRequest', 'fieldViolations' => []]],
            ],
        ];
        $response = new Response(400, [], json_encode($payload));
        $exception = new ClientException('bad request', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($payload): bool {
                if ($message !== 'FirebaseService: FCM ClientException (HTTP 400)') {
                    return false;
                }
                if (($context['device_token'] ?? null) !== 'tok') {
                    return false;
                }
                if (($context['device_type'] ?? null) !== 'android') {
                    return false;
                }
                if (($context['status_code'] ?? null) !== 400) {
                    return false;
                }
                if (($context['url'] ?? '') !== 'https://fcm.googleapis.com/v1/projects/myproj/messages:send') {
                    return false;
                }
                if (($context['project_name'] ?? null) !== 'myproj') {
                    return false;
                }
                if (($context['error_message'] ?? '') !== 'bad request') {
                    return false;
                }
                if (($context['fcm_error_code'] ?? null) !== 400) {
                    return false;
                }
                if (($context['fcm_error_message'] ?? null) !== 'Invalid JSON') {
                    return false;
                }
                if (($context['fcm_error_status'] ?? null) !== 'INVALID_ARGUMENT') {
                    return false;
                }
                if (($context['fcm_error_details'] ?? null) != $payload['error']['details']) {
                    return false;
                }
                if (($context['full_error_response'] ?? null) != $payload) {
                    return false;
                }
                if (($context['request_payload']['message']['token'] ?? null) !== 'tok') {
                    return false;
                }
                if (! is_string($context['error_trace'] ?? null) || $context['error_trace'] === '') {
                    return false;
                }

                return true;
            });

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');

        $this->expectException(ClientException::class);
        $service->sendNotification('tok', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_client_exception_without_top_level_error_key_nulls_fcm_fields(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $payload = ['status' => 'ignored', 'note' => 'no error key'];
        $response = new Response(400, [], json_encode($payload));
        $exception = new ClientException('bad', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'FirebaseService: FCM ClientException (HTTP 400)',
                Mockery::on(function ($context) use ($payload): bool {
                    if (! is_array($context)) {
                        return false;
                    }
                    if (($context['fcm_error_code'] ?? null) !== null) {
                        return false;
                    }
                    if (($context['fcm_error_message'] ?? null) !== null) {
                        return false;
                    }
                    if (($context['fcm_error_status'] ?? null) !== null) {
                        return false;
                    }
                    if (($context['fcm_error_details'] ?? null) !== null) {
                        return false;
                    }
                    if (($context['full_error_response'] ?? null) != $payload) {
                        return false;
                    }
                    if (($context['url'] ?? '') !== 'https://fcm.googleapis.com/v1/projects/myproj/messages:send') {
                        return false;
                    }
                    if (($context['project_name'] ?? null) !== 'myproj') {
                        return false;
                    }

                    return true;
                })
            );

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');

        $this->expectException(ClientException::class);
        $service->sendNotification('tok', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_client_exception_parse_failure_falls_back_to_raw_body_string(): void
    {
        $stream = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $stream->shouldReceive('getContents')->once()->andThrow(new \RuntimeException('stream read failed'));
        $stream->shouldReceive('getContents')->once()->andReturn('not-json{');

        $response = Mockery::mock(\Psr\Http\Message\ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(400);
        $response->shouldReceive('getBody')->andReturn($stream);

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $exception = new ClientException('bad', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'FirebaseService: FCM ClientException (HTTP 400)',
                Mockery::on(function ($context): bool {
                    if (! is_array($context)) {
                        return false;
                    }

                    return ($context['full_error_response'] ?? null) === 'not-json{'
                        && ($context['fcm_error_code'] ?? null) === null;
                })
            );

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'myproj');

        $this->expectException(ClientException::class);
        $service->sendNotification('tok', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_request_exception_logs_full_context_with_decoded_body(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/p/messages:send');
        $response = new Response(503, [], json_encode(['reason' => 'overloaded']));
        $exception = new RequestException('down', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'FirebaseService: FCM RequestException'
                    && ($context['device_token'] ?? null) === 'tok2'
                    && ($context['device_type'] ?? null) === 'android'
                    && ($context['status_code'] ?? null) === 503
                    && ($context['url'] ?? '') === 'https://fcm.googleapis.com/v1/projects/p/messages:send'
                    && ($context['project_name'] ?? null) === 'p'
                    && ($context['error_message'] ?? '') === 'down'
                    && ($context['full_error_response'] ?? null) === ['reason' => 'overloaded']
                    && ($context['request_payload']['message']['token'] ?? null) === 'tok2'
                    && is_string($context['error_trace'] ?? null)
                    && $context['error_trace'] !== '';
            });

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $this->expectException(RequestException::class);
        $service->sendNotification('tok2', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_request_exception_without_response_leaves_status_and_body_null(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/p/messages:send');
        $exception = new RequestException('network', $request, null);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'FirebaseService: FCM RequestException',
                Mockery::on(function ($context): bool {
                    if (! is_array($context)) {
                        return false;
                    }

                    return ($context['status_code'] ?? null) === null
                        && ($context['full_error_response'] ?? null) === null
                        && ($context['device_token'] ?? null) === 't3'
                        && ($context['url'] ?? '') === 'https://fcm.googleapis.com/v1/projects/p/messages:send'
                        && ($context['project_name'] ?? null) === 'p';
                })
            );

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $this->expectException(RequestException::class);
        $service->sendNotification('t3', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_request_exception_invalid_json_body_preserves_null_decoded_payload(): void
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/p/messages:send');
        $response = new Response(502, [], '<<not-json>>');
        $exception = new RequestException('bad gateway', $request, $response);

        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow($exception);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'FirebaseService: FCM RequestException',
                Mockery::on(function ($context): bool {
                    if (! is_array($context)) {
                        return false;
                    }

                    return ($context['status_code'] ?? null) === 502
                        && ($context['full_error_response'] ?? null) === null;
                })
            );

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $this->expectException(RequestException::class);
        $service->sendNotification('t4', ['title' => 'a', 'body' => 'b'], [], 'android');
    }

    public function test_send_notification_generic_exception_logs_notification_and_data_context(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow(new \RuntimeException('boom'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'FirebaseService: Error sending notification'
                    && ($context['device_token'] ?? null) === 'dt'
                    && ($context['device_type'] ?? null) === 'android'
                    && ($context['url'] ?? '') === 'https://fcm.googleapis.com/v1/projects/p/messages:send'
                    && ($context['project_name'] ?? null) === 'p'
                    && ($context['error'] ?? '') === 'boom'
                    && ($context['notification'] ?? null) === ['title' => 'T', 'body' => 'B']
                    && ($context['data'] ?? null) === ['x' => 1]
                    && is_string($context['error_trace'] ?? null);
            });

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $this->expectException(\RuntimeException::class);
        $service->sendNotification('dt', ['title' => 'T', 'body' => 'B'], ['x' => 1], 'android');
    }

    public function test_invalidate_token_posts_invalidate_payload_to_fcm_and_returns_true(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')
            ->once()
            ->withArgs(function (string $url, array $options): bool {
                if ($url !== 'https://fcm.googleapis.com/v1/projects/pinv/messages:send') {
                    return false;
                }
                if (($options['headers']['Authorization'] ?? '') !== 'Bearer tok-inv') {
                    return false;
                }
                if (($options['headers']['Content-Type'] ?? '') !== 'application/json') {
                    return false;
                }
                $msg = $options['json']['message'] ?? [];

                return ($msg['token'] ?? null) === 'fcm-device'
                    && ($msg['webpush']['notification']['title'] ?? null) === ''
                    && ($msg['webpush']['notification']['body'] ?? null) === ''
                    && ($msg['data']['invalidate'] ?? null) === 'true';
            })
            ->andReturn(new Response(200, [], '{}'));

        $service = new FirebaseServiceHarness($http, ['access_token' => 'tok-inv'], 'pinv');

        $m = new ReflectionMethod(FirebaseService::class, 'invalidateToken');
        $m->setAccessible(true);

        $this->assertTrue($m->invoke($service, 'fcm-device'));
    }

    public function test_invalidate_token_returns_false_when_post_throws(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('post')->once()->andThrow(new \RuntimeException('fail'));

        $service = new FirebaseServiceHarness($http, ['access_token' => 't'], 'p');

        $m = new ReflectionMethod(FirebaseService::class, 'invalidateToken');
        $m->setAccessible(true);

        $this->assertFalse($m->invoke($service, 'any'));
    }
}
