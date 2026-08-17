<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\ClientLogSanitizer;
use Tests\TestCase;

class ClientLogSanitizerTest extends TestCase
{
    public function test_sanitize_string_strips_html_tags(): void
    {
        $this->assertSame(
            'alert(1)',
            ClientLogSanitizer::sanitizeString('<script>alert(1)</script>')
        );
    }

    public function test_sanitize_string_removes_control_characters(): void
    {
        $this->assertSame(
            'hello world',
            ClientLogSanitizer::sanitizeString("hello\x00world\x1F")
        );
    }

    public function test_sanitize_string_truncates_to_max_length(): void
    {
        $this->assertSame(
            str_repeat('a', 100),
            ClientLogSanitizer::sanitizeString(str_repeat('a', 150), 100)
        );
    }

    public function test_sanitize_string_returns_null_for_empty_input(): void
    {
        $this->assertNull(ClientLogSanitizer::sanitizeString(''));
        $this->assertNull(ClientLogSanitizer::sanitizeString(null));
    }

    public function test_sanitize_context_keeps_scalar_values_and_drops_nested_objects(): void
    {
        $result = ClientLogSanitizer::sanitizeContext([
            'status' => 422,
            'source' => 'trip_create',
            'nested' => ['danger' => '<img onerror=alert(1)>'],
            'errors' => ['car_id' => ['missing plate']],
        ]);

        $this->assertSame(422, $result['status']);
        $this->assertSame('trip_create', $result['source']);
        $this->assertArrayNotHasKey('nested', $result);
        $this->assertSame(['car_id' => ['missing plate']], $result['errors']);
    }

    public function test_sanitize_context_returns_null_for_non_array_input(): void
    {
        $this->assertNull(ClientLogSanitizer::sanitizeContext('not-an-array'));
    }
}
