<?php

namespace Tests\Unit\Support;

use STS\Support\SupportTicketOpeningAutoReply;
use Tests\TestCase;

class SupportTicketOpeningAutoReplyTest extends TestCase
{
    public function test_markdown_contains_expected_greeting_and_team_signoff(): void
    {
        $markdown = SupportTicketOpeningAutoReply::MARKDOWN;

        $this->assertStringContainsString('¡Hola!', $markdown);
        $this->assertStringContainsString('Equipo Carpoolear', $markdown);
        $this->assertStringContainsString('preguntas frecuentes', $markdown);
    }
}
