<?php

namespace STS\Support;

final class SupportTicketMessage
{
    public const SUPPORT_INFO_SECTION_HEADER = '--- Información del dispositivo ---';

    public static function stripSupportInfo(string $message): string
    {
        $headerIndex = strpos($message, self::SUPPORT_INFO_SECTION_HEADER);
        if ($headerIndex === false) {
            return trim($message);
        }

        return trim(substr($message, 0, $headerIndex));
    }

    public static function hasUserContent(string $message): bool
    {
        return self::stripSupportInfo($message) !== '';
    }

    public static function userContentValidationRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! self::hasUserContent((string) $value)) {
                $fail('The message markdown field is required.');
            }
        };
    }
}
