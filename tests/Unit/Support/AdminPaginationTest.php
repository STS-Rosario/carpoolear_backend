<?php

namespace Tests\Unit\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use STS\Support\AdminPagination;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    public function test_default_per_page_is_twenty(): void
    {
        $this->assertSame(20, AdminPagination::DEFAULT_PER_PAGE);
    }

    public function test_allowed_per_page_options(): void
    {
        $this->assertSame([10, 20, 30, 50, 100], AdminPagination::ALLOWED_PER_PAGE_OPTIONS);
    }

    public function test_resolve_per_page_uses_default_when_missing(): void
    {
        $this->assertSame(20, AdminPagination::resolvePerPage(null));
    }

    public function test_resolve_per_page_accepts_allowed_values(): void
    {
        foreach ([10, 20, 30, 50, 100] as $perPage) {
            $this->assertSame($perPage, AdminPagination::resolvePerPage($perPage));
        }
    }

    public function test_resolve_per_page_falls_back_to_default_for_invalid_values(): void
    {
        $this->assertSame(20, AdminPagination::resolvePerPage(0));
        $this->assertSame(20, AdminPagination::resolvePerPage(15));
        $this->assertSame(20, AdminPagination::resolvePerPage(500));
    }

    public function test_resolve_page_defaults_to_one_and_clamps_to_minimum_one(): void
    {
        $this->assertSame(1, AdminPagination::resolvePage(null));
        $this->assertSame(1, AdminPagination::resolvePage(0));
        $this->assertSame(3, AdminPagination::resolvePage(3));
    }

    public function test_pagination_meta_from_paginator(): void
    {
        $paginator = new LengthAwarePaginator(['a', 'b'], 25, 10, 2);

        $this->assertSame([
            'current_page' => 2,
            'per_page' => 10,
            'total' => 25,
            'total_pages' => 3,
        ], AdminPagination::paginationMeta($paginator));
    }
}
