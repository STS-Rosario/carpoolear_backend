<?php

namespace STS\Support;

final class FacebookProfileUrl
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'facebook.com',
        'www.facebook.com',
        'm.facebook.com',
        'mbasic.facebook.com',
    ];

    /**
     * @return string|null Canonical https://facebook.com/... URL, or null when empty/invalid
     */
    public static function tryNormalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $withScheme = $trimmed;
        if (! preg_match('#^https?://#i', $withScheme)) {
            $withScheme = 'https://'.ltrim($withScheme, '/');
        }

        $parts = parse_url($withScheme);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        if (! self::isAllowedHost($host)) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        return 'https://facebook.com'.$path.$query.$fragment;
    }

    public static function isAllowedHost(string $host): bool
    {
        return in_array(strtolower($host), self::ALLOWED_HOSTS, true);
    }
}
