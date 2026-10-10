<?php

namespace Tests\Unit\Support;

use Illuminate\Http\Request;
use STS\Support\JwtTokenDebugContext;
use Tests\TestCase;

class JwtTokenDebugContextTest extends TestCase
{
    public function test_for_request_describes_malformed_bearer_token_without_leaking_full_value(): void
    {
        $request = Request::create('/api/trips', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer not-a-jwt',
            'HTTP_USER_AGENT' => 'TestAgent/1.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $context = JwtTokenDebugContext::forRequest($request, null);

        $this->assertSame('GET', $context['method']);
        $this->assertSame('api/trips', $context['path']);
        $this->assertSame('203.0.113.10', $context['ip']);
        $this->assertSame('TestAgent/1.0', $context['user_agent']);
        $this->assertSame('Bearer', $context['authorization_scheme']);
        $this->assertNull($context['token_length']);
        $this->assertNull($context['token_segments']);
        $this->assertSame('Bearer not-a-jwt', $context['token_preview']);
    }

    public function test_for_request_redacts_long_authorization_header_values(): void
    {
        $longToken = str_repeat('a', 80);
        $request = Request::create('/api/profile', 'POST');
        $request->headers->set('Authorization', 'Bearer '.$longToken);

        $context = JwtTokenDebugContext::forRequest($request, null);

        $this->assertNull($context['token_length']);
        $this->assertNull($context['token_segments']);
        $this->assertStringContainsString('...', $context['token_preview']);
        $this->assertStringContainsString('87 chars', $context['token_preview']);
        $this->assertStringNotContainsString($longToken, $context['token_preview']);
    }
}
