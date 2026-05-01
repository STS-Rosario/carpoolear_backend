<?php

namespace Tests\Unit\Services\HttpClients;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use STS\Services\HttpClients\DevFacebookCurlSsl;
use Tests\TestCase;

class DevFacebookCurlSslTest extends TestCase
{
    public function test_apply_insecure_ssl_logs_warning_when_handle_is_missing(): void
    {
        Event::fake([MessageLogged::class]);

        DevFacebookCurlSsl::applyInsecureSslOrLogMissing(null);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'warning'
                && str_contains((string) $e->message, 'Could not find cURL handle');
        });
    }

    public function test_apply_insecure_ssl_does_not_warn_when_handle_is_curl_handle(): void
    {
        Event::fake([MessageLogged::class]);

        $ch = curl_init('https://example.test/');
        $this->assertNotFalse($ch);

        DevFacebookCurlSsl::applyInsecureSslOrLogMissing($ch);

        curl_close($ch);

        Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'warning'
                && str_contains((string) $e->message, 'Could not find cURL handle');
        });
    }

    public function test_is_usable_curl_handle_accepts_curl_handle_and_curl_resource(): void
    {
        $ch = curl_init('https://example.test/');
        $this->assertTrue(DevFacebookCurlSsl::isUsableCurlHandle($ch));
        curl_close($ch);

        $this->assertFalse(DevFacebookCurlSsl::isUsableCurlHandle(null));
        $this->assertFalse(DevFacebookCurlSsl::isUsableCurlHandle('not-a-handle'));
    }
}
