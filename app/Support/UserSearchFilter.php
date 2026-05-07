<?php

namespace STS\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Branched user search for admin list and /api/users/search:
 * - Numeric-only term: id (as string), nro_doc, mobile_phone — LIKE.
 * - Otherwise: name, email, nro_doc — LIKE (mobile_phone excluded).
 */
final class UserSearchFilter
{
    public static function apply(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        if (ctype_digit($term)) {
            $query->where(function (Builder $q) use ($like) {
                $q->whereRaw('CAST(id AS CHAR) LIKE ?', [$like])
                    ->orWhere('nro_doc', 'like', $like)
                    ->orWhere('mobile_phone', 'like', $like);
            });
        } else {
            $query->where(function (Builder $q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('nro_doc', 'like', $like);
            });
        }
    }
}
