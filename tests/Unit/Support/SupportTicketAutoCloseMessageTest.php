<?php

namespace Tests\Unit\Support;

use STS\Support\SupportTicketAutoCloseMessage;
use Tests\TestCase;

class SupportTicketAutoCloseMessageTest extends TestCase
{
    public function test_markdown_contains_expected_closing_guidance_and_footer(): void
    {
        $markdown = SupportTicketAutoCloseMessage::MARKDOWN;

        $this->assertStringContainsString(
            'No te preocupes: si todavía necesitás ayuda, podés abrir un nuevo ticket, sea por este o algún otro tema.',
            $markdown
        );
        $this->assertStringContainsString('Equipo Carpoolear', $markdown);
        $this->assertStringContainsString('[APORTAR]', $markdown);
        $this->assertStringContainsString('https://carpoolear.com.ar/aportar', $markdown);
        $this->assertStringNotContainsString('volvemos a abrir', $markdown);
    }

    public function test_project_footer_uses_italic_markdown_not_nested_bracket_links(): void
    {
        $markdown = SupportTicketAutoCloseMessage::MARKDOWN;

        $this->assertStringContainsString(
            '*Carpoolear es un proyecto de gestión colectiva',
            $markdown
        );
        $this->assertStringNotContainsString(
            '[Carpoolear es un proyecto de gestión colectiva',
            $markdown
        );
    }

    public function test_for_user_substitutes_name_or_default(): void
    {
        $this->assertStringContainsString('Buenas Ana', SupportTicketAutoCloseMessage::forUser('Ana'));
        $this->assertStringContainsString('Buenas de nuevo', SupportTicketAutoCloseMessage::forUser(''));
    }
}
