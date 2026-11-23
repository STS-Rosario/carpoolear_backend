<?php

namespace Tests\Unit\Support;

use STS\Support\SupportTicketAttachmentRules;
use Tests\TestCase;

class SupportTicketAttachmentRulesTest extends TestCase
{
    public function test_default_max_is_six(): void
    {
        config()->offsetUnset('carpoolear.support_ticket_max_attachments');

        $this->assertSame(6, SupportTicketAttachmentRules::maxAttachments());
        $this->assertSame('nullable|array|max:6', SupportTicketAttachmentRules::attachmentsArrayRule());
    }

    public function test_respects_config_within_hard_cap(): void
    {
        config()->set('carpoolear.support_ticket_max_attachments', 8);

        $this->assertSame(8, SupportTicketAttachmentRules::maxAttachments());
    }

    public function test_clamps_below_one_and_above_hard_cap(): void
    {
        config()->set('carpoolear.support_ticket_max_attachments', 0);
        $this->assertSame(1, SupportTicketAttachmentRules::maxAttachments());

        config()->set('carpoolear.support_ticket_max_attachments', 99);
        $this->assertSame(20, SupportTicketAttachmentRules::maxAttachments());
    }
}