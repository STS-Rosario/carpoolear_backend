<?php

namespace STS\Support;

use Illuminate\Support\Facades\Cache;

class NotificationCountCache
{
    public static function key(int $userId): string
    {
        return "user:{$userId}:notification_unread_count";
    }

    public static function remember(int $userId, callable $callback): int
    {
        return (int) Cache::rememberForever(self::key($userId), $callback);
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }
}
