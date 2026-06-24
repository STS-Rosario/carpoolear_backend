<?php

namespace STS\Support;

use STS\Models\User;

class UserLocale
{
    public static function resolve(?User $user, ?string $fallbackLocale = null): string
    {
        $locale = $user?->locale;

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        if (is_string($fallbackLocale) && $fallbackLocale !== '') {
            return $fallbackLocale;
        }

        return (string) config('app.locale');
    }

    public static function withLocale(string $locale, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
