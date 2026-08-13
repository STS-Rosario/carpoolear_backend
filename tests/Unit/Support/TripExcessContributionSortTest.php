<?php

namespace Tests\Unit\Support;

use Illuminate\Database\Eloquent\Builder;
use STS\Support\TripExcessContributionSort;
use Tests\TestCase;

class TripExcessContributionSortTest extends TestCase
{
    public function test_resolve_sort_accepts_allowed_columns(): void
    {
        $this->assertSame('id', TripExcessContributionSort::resolveSort('id'));
        $this->assertSame('user_name', TripExcessContributionSort::resolveSort('user_name'));
        $this->assertSame(
            'excess_contribution_support_tickets_count',
            TripExcessContributionSort::resolveSort('excess_contribution_support_tickets_count')
        );
    }

    public function test_resolve_sort_returns_null_for_invalid_columns(): void
    {
        $this->assertNull(TripExcessContributionSort::resolveSort('not_a_column'));
        $this->assertNull(TripExcessContributionSort::resolveSort(null));
    }

    public function test_apply_requires_action_only_filters_terminal_statuses(): void
    {
        $query = TripExcessContributionSort::applyRequiresActionOnlyFilter(
            \STS\Models\Trip::query()
        );

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('exceso_contribucion_status', $sql);
        $this->assertStringContainsString('is null', $sql);
        $this->assertStringContainsString('in (?, ?)', $sql);
    }

    public function test_apply_orders_by_id_when_sort_is_id(): void
    {
        $query = TripExcessContributionSort::apply(
            \STS\Models\Trip::query(),
            'id',
            'desc'
        );

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertStringContainsString('order by `trips`.`id` desc', strtolower($query->toSql()));
    }
}
