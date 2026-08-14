<?php

namespace App\Helpers;

class DocumentIdHelper
{
    public static function patterns(): array
    {
        $formats = config('carpoolear.profile_id_formats');
        if (is_array($formats) && count($formats) > 0) {
            return array_values(array_filter(array_map(function ($entry) {
                if (is_array($entry)) {
                    return (string) ($entry['pattern'] ?? '');
                }

                return (string) $entry;
            }, $formats)));
        }

        return [(string) config('carpoolear.profile_id_format', '##.###.###')];
    }

    public static function cleanValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '');
    }

    /**
     * @return array{formatted: string, consumed: int, complete: bool}
     */
    public static function matchPattern(string $cleaned, string $pattern): array
    {
        $formatted = '';
        $cleanedIndex = 0;
        $length = strlen($cleaned);
        $patternLength = strlen($pattern);

        for ($i = 0; $i < $patternLength; $i++) {
            if ($cleanedIndex >= $length) {
                break;
            }

            $slot = $pattern[$i];
            $char = $cleaned[$cleanedIndex];

            if ($slot === '#') {
                if (! ctype_digit($char)) {
                    return [
                        'formatted' => $formatted,
                        'consumed' => $cleanedIndex,
                        'complete' => false,
                    ];
                }
                $formatted .= $char;
                $cleanedIndex++;

                continue;
            }

            if ($slot === 'A') {
                if (! ctype_alpha($char)) {
                    return [
                        'formatted' => $formatted,
                        'consumed' => $cleanedIndex,
                        'complete' => false,
                    ];
                }
                $formatted .= strtoupper($char);
                $cleanedIndex++;

                continue;
            }

            $formatted .= $slot;
        }

        return [
            'formatted' => $formatted,
            'consumed' => $cleanedIndex,
            'complete' => $cleanedIndex === $length,
        ];
    }

    public static function isValid(?string $value): bool
    {
        $cleaned = self::cleanValue($value);
        if ($cleaned === '') {
            return false;
        }

        foreach (self::patterns() as $pattern) {
            $match = self::matchPattern($cleaned, $pattern);
            if ($match['complete']) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeForStorage(?string $value): ?string
    {
        $cleaned = self::cleanValue($value);
        if ($cleaned === '') {
            return null;
        }

        if (! self::isValid($value)) {
            return null;
        }

        return $cleaned;
    }

    public static function normalizeForBanCheck(?string $value): ?string
    {
        $cleaned = self::cleanValue($value);
        if ($cleaned === '') {
            return null;
        }

        return $cleaned;
    }
}
