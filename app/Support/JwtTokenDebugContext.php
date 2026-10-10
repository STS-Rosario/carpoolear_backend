<?php

namespace STS\Support;

use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;

class JwtTokenDebugContext
{
    public static function forRequest(Request $request, ?JWTAuth $auth): array
    {
        $authHeader = $request->header('Authorization');
        $rawToken = null;

        if ($auth) {
            try {
                $rawToken = $auth->parser()->setRequest($request)->parseToken();
            } catch (\Throwable) {
                // Parser may fail before JWT structure validation; context still helps.
            }
        }

        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'authorization_scheme' => self::authorizationScheme($authHeader),
            'token_length' => $rawToken !== null ? strlen($rawToken) : null,
            'token_segments' => $rawToken !== null ? count(explode('.', $rawToken)) : null,
            'token_preview' => self::tokenPreview($rawToken ?? $authHeader),
        ];
    }

    private static function authorizationScheme(?string $header): ?string
    {
        if ($header === null || $header === '') {
            return null;
        }

        $parts = explode(' ', trim($header), 2);

        return $parts[0] ?? null;
    }

    private static function tokenPreview(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '(empty)';
        }

        if (strlen($value) <= 32) {
            return $value;
        }

        return substr($value, 0, 8).'...'.substr($value, -4).' ('.strlen($value).' chars)';
    }
}
