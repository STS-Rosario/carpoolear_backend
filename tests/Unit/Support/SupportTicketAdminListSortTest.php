<?php

namespace Tests\Unit\Support;

use Illuminate\Database\Eloquent\Builder;
use STS\Models\DonationSubscription;
use STS\Models\SupportTicket;
use STS\Models\User;
use STS\Support\SupportTicketAdminListSort;
use Tests\TestCase;

class SupportTicketAdminListSortTest extends TestCase
{
    public function test_resolve_sort_accepts_allowed_columns(): void
    {
        foreach ([
            'subject',
            'priority',
            'created_at',
            'updated_at',
            'status',
            'assigned_to',
            'type',
        ] as $sort) {
            $this->assertSame($sort, SupportTicketAdminListSort::resolveSort($sort));
        }
    }

    public function test_resolve_sort_returns_null_for_invalid_columns(): void
    {
        $this->assertNull(SupportTicketAdminListSort::resolveSort('not_a_column'));
        $this->assertNull(SupportTicketAdminListSort::resolveSort('club'));
        $this->assertNull(SupportTicketAdminListSort::resolveSort(null));
    }

    public function test_resolve_direction_defaults_to_desc_and_accepts_asc(): void
    {
        $this->assertSame('desc', SupportTicketAdminListSort::resolveDirection(null));
        $this->assertSame('asc', SupportTicketAdminListSort::resolveDirection('asc'));
        $this->assertSame('desc', SupportTicketAdminListSort::resolveDirection('DESC'));
        $this->assertSame('desc', SupportTicketAdminListSort::resolveDirection('nope'));
    }

    public function test_apply_orders_open_club_members_first_then_id_desc_by_default(): void
    {
        $query = SupportTicketAdminListSort::apply(SupportTicket::query(), null, null);
        $sql = strtolower($query->toSql());

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertStringContainsString("status not in ('resuelto', 'cerrado')", $sql);
        $this->assertStringContainsString('monthly_donate', $sql);
        $this->assertStringContainsString('donation_subscriptions', $sql);
        $this->assertStringContainsString('authorized', $sql);
        $this->assertMatchesRegularExpression('/order by .+ `support_tickets`\\.`id` desc/', $sql);
    }

    public function test_apply_orders_by_priority_high_normal_low_after_club(): void
    {
        $query = SupportTicketAdminListSort::apply(SupportTicket::query(), 'priority', 'desc');
        $sql = strtolower($query->toSql());

        $this->assertStringContainsString("status not in ('resuelto', 'cerrado')", $sql);
        $this->assertStringContainsString("when `support_tickets`.`priority` = 'high' then 3", $sql);
        $this->assertStringContainsString("when `support_tickets`.`priority` = 'normal' then 2", $sql);
        $this->assertStringContainsString("when `support_tickets`.`priority` = 'low' then 1", $sql);
    }

    public function test_decorate_club_active_uses_current_membership_not_public_flag(): void
    {
        $hiddenMember = User::factory()->create([
            'monthly_donate' => true,
            'show_club_carpoolear_membership' => false,
        ]);
        $subscriber = User::factory()->create(['monthly_donate' => false]);
        DonationSubscription::create([
            'user_id' => $subscriber->id,
            'status' => 'authorized',
            'transaction_amount_cents' => 500000,
        ]);
        $outsider = User::factory()->create(['monthly_donate' => false]);

        $tickets = [
            new SupportTicket(['user_id' => $hiddenMember->id]),
            new SupportTicket(['user_id' => $subscriber->id]),
            new SupportTicket(['user_id' => $outsider->id]),
        ];

        SupportTicketAdminListSort::decorateClubActive($tickets);

        $this->assertSame(1, $tickets[0]->club_carpoolear_active);
        $this->assertSame(1, $tickets[1]->club_carpoolear_active);
        $this->assertSame(0, $tickets[2]->club_carpoolear_active);
    }
}
