<?php

namespace STS\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Naive DATETIME columns are stored and read as UTC wall times (product requirement).
 */
class UtcDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Carbon) {
            return [$key => $value->copy()->utc()->format('Y-m-d H:i:s')];
        }

        return [$key => Carbon::parse($value, 'UTC')->format('Y-m-d H:i:s')];
    }
}
