<?php

namespace STS\Helpers;

class ClientLogSanitizer
{
    public static function sanitizeString(?string $value, int $maxLength = 4000): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = self::removeScriptAndStyleBlocks($value);
        $value = strip_tags($value);
        $value = preg_replace('/[\r\n]+/', ' ', $value) ?? '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * @param  mixed  $context
     * @return array<string, mixed>|null
     */
    public static function sanitizeContext($context, int $maxDepth = 2): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        return self::sanitizeContextLevel($context, 0, $maxDepth);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function sanitizeContextLevel(array $context, int $depth, int $maxDepth): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $safeKey = self::sanitizeString((string) $key, 100);
            if ($safeKey === null) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                if ($value === null) {
                    $sanitized[$safeKey] = null;

                    continue;
                }

                $stringValue = self::sanitizeString((string) $value, 1000);
                if ($stringValue !== null) {
                    $sanitized[$safeKey] = is_numeric($value) && ! is_string($value)
                        ? $value + 0
                        : $stringValue;
                }

                continue;
            }

            if ($depth >= $maxDepth) {
                continue;
            }

            if (is_array($value)) {
                $nested = self::sanitizeContextLevel($value, $depth + 1, $maxDepth);
                if ($nested !== []) {
                    $sanitized[$safeKey] = $nested;
                }
            }
        }

        return $sanitized;
    }

    private static function removeScriptAndStyleBlocks(string $value): string
    {
        $value = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value) ?? '';
        $value = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $value) ?? '';

        return $value;
    }
}
