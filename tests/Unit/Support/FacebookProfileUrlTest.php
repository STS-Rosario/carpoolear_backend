<?php

namespace Tests\Unit\Support;

use STS\Support\FacebookProfileUrl;
use Tests\TestCase;

class FacebookProfileUrlTest extends TestCase
{
    public function test_try_normalize_returns_null_for_empty_values(): void
    {
        $this->assertNull(FacebookProfileUrl::tryNormalize(null));
        $this->assertNull(FacebookProfileUrl::tryNormalize(''));
        $this->assertNull(FacebookProfileUrl::tryNormalize('   '));
    }

    /**
     * @dataProvider validFacebookProfileUrlsProvider
     */
    public function test_try_normalize_canonicalizes_facebook_profile_urls(
        string $input,
        string $expected
    ): void {
        $this->assertSame($expected, FacebookProfileUrl::tryNormalize($input));
    }

    public static function validFacebookProfileUrlsProvider(): array
    {
        return [
            'without scheme' => ['facebook.com/test-user', 'https://facebook.com/test-user'],
            'with www' => ['www.facebook.com/test-user', 'https://facebook.com/test-user'],
            'with https www' => ['https://www.facebook.com/test-user', 'https://facebook.com/test-user'],
            'already canonical' => ['https://facebook.com/test-user', 'https://facebook.com/test-user'],
            'root host only' => ['facebook.com', 'https://facebook.com/'],
            'mobile host' => ['m.facebook.com/test-user', 'https://facebook.com/test-user'],
        ];
    }

    /**
     * @dataProvider invalidFacebookProfileUrlsProvider
     */
    public function test_try_normalize_rejects_non_facebook_urls(string $input): void
    {
        $this->assertNull(FacebookProfileUrl::tryNormalize($input));
    }

    public static function invalidFacebookProfileUrlsProvider(): array
    {
        return [
            'other domain' => ['https://example.com/profile'],
            'lookalike domain' => ['https://facebook.com.evil.com/profile'],
            'not a url' => ['not-a-url'],
        ];
    }
}
