<?php

namespace STS\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminPagination
{
    public const DEFAULT_PER_PAGE = 20;

    /** @var list<int> */
    public const ALLOWED_PER_PAGE_OPTIONS = [10, 20, 30, 50, 100];

    public static function resolvePerPage(mixed $value): int
    {
        $perPage = (int) $value;

        if (in_array($perPage, self::ALLOWED_PER_PAGE_OPTIONS, true)) {
            return $perPage;
        }

        return self::DEFAULT_PER_PAGE;
    }

    public static function resolvePage(mixed $value): int
    {
        $page = (int) $value;

        return max($page, 1);
    }

    /**
     * @return array{current_page: int, per_page: int, total: int, total_pages: int}
     */
    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];
    }
}
