<?php

namespace Tests\Unit\Support;

use STS\Support\SupportTicketMessage;
use Tests\TestCase;

class SupportTicketMessageTest extends TestCase
{
    public function test_strip_support_info_returns_user_content_before_device_section(): void
    {
        $message = "Need help with login\n\n".SupportTicketMessage::SUPPORT_INFO_SECTION_HEADER."\nApp Version: 120";

        $this->assertSame('Need help with login', SupportTicketMessage::stripSupportInfo($message));
    }

    public function test_strip_support_info_returns_empty_string_when_only_device_section_is_present(): void
    {
        $message = SupportTicketMessage::SUPPORT_INFO_SECTION_HEADER."\nApp Version: 120\nPlatform: web";

        $this->assertSame('', SupportTicketMessage::stripSupportInfo($message));
    }

    public function test_has_user_content_rejects_support_info_only_messages(): void
    {
        $message = SupportTicketMessage::SUPPORT_INFO_SECTION_HEADER."\nApp Version: 120";

        $this->assertFalse(SupportTicketMessage::hasUserContent($message));
    }

    public function test_has_user_content_accepts_messages_with_user_text(): void
    {
        $message = "Need help\n\n".SupportTicketMessage::SUPPORT_INFO_SECTION_HEADER."\nApp Version: 120";

        $this->assertTrue(SupportTicketMessage::hasUserContent($message));
    }
}
