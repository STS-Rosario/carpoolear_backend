<?php

namespace Tests\Unit\Support;

use Illuminate\Database\Eloquent\Builder;
use STS\Support\ManualIdentityValidationSort;
use Tests\TestCase;

class ManualIdentityValidationSortTest extends TestCase
{
    public function test_resolve_sort_accepts_allowed_columns(): void
    {
        $this->assertSame('id', ManualIdentityValidationSort::resolveSort('id'));
        $this->assertSame('user_name', ManualIdentityValidationSort::resolveSort('user_name'));
        $this->assertSame('waiting_time', ManualIdentityValidationSort::resolveSort('waiting_time'));
        $this->assertSame(
            'open_account_verification_tickets_count',
            ManualIdentityValidationSort::resolveSort('open_account_verification_tickets_count')
        );
    }

    public function test_resolve_sort_returns_null_for_invalid_columns(): void
    {
        $this->assertNull(ManualIdentityValidationSort::resolveSort('not_a_column'));
        $this->assertNull(ManualIdentityValidationSort::resolveSort(null));
    }

    public function test_resolve_direction_defaults_to_desc_and_accepts_asc(): void
    {
        $this->assertSame('desc', ManualIdentityValidationSort::resolveDirection(null));
        $this->assertSame('asc', ManualIdentityValidationSort::resolveDirection('asc'));
        $this->assertSame('desc', ManualIdentityValidationSort::resolveDirection('DESC'));
    }

    public function test_apply_uses_default_queue_order_when_sort_is_missing(): void
    {
        $query = ManualIdentityValidationSort::apply(
            \STS\Models\ManualIdentityValidation::query(),
            null,
            'asc'
        );

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertStringContainsString('CASE WHEN paid = 1 THEN 0 ELSE 1 END', $query->toSql());
    }

    public function test_apply_orders_by_id_when_sort_is_id(): void
    {
        $query = ManualIdentityValidationSort::apply(
            \STS\Models\ManualIdentityValidation::query(),
            'id',
            'desc'
        );

        $this->assertStringContainsString('order by `manual_identity_validations`.`id` desc', strtolower($query->toSql()));
    }

    public function test_apply_orders_by_open_account_verification_ticket_count_and_workflow_state(): void
    {
        $query = ManualIdentityValidationSort::apply(
            \STS\Models\ManualIdentityValidation::query(),
            'open_account_verification_tickets_count',
            'desc'
        );

        $sql = strtolower($query->toSql());

        $this->assertStringContainsString('open_account_verification_tickets_count', $sql);
        $this->assertStringContainsString('when manual_identity_validations.paid = 0 then 0', $sql);
        $this->assertStringContainsString('coalesce(open_account_verification_tickets_count, 0) desc', $sql);
    }
}
