<?php

namespace STS\Support;

use Illuminate\Http\Request;

final class BannedUserAccess
{
    public static function allows(Request $request): bool
    {
        $method = strtoupper($request->method());

        if ($method === 'POST' && $request->is('api/logout', 'api/retoken')) {
            return true;
        }

        if ($method === 'GET' && $request->is('api/users/me')) {
            return true;
        }

        if ($request->is('api/support/tickets')) {
            return $method === 'GET';
        }

        if ($request->is('api/support/tickets/*')) {
            return in_array($method, ['GET', 'POST'], true);
        }

        return false;
    }
}
