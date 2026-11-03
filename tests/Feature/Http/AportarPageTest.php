<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class AportarPageTest extends TestCase
{
    public function test_aportar_redirects_to_in_app_donate_page(): void
    {
        $this->get('/aportar')
            ->assertRedirect('/app/donate')
            ->assertStatus(301);
    }

    public function test_aportar_preserves_query_string_when_redirecting(): void
    {
        $this->get('/aportar?u=42')
            ->assertRedirect('/app/donate?u=42')
            ->assertStatus(301);
    }

    public function test_legacy_donar_still_redirects_through_aportar(): void
    {
        $this->get('/donar')->assertRedirect('/aportar');
    }

    public function test_donar_compartir_uses_tracked_checkout(): void
    {
        $html = $this->get('/donar-compartir')->assertOk()->getContent();

        $this->assertStringContainsString('/api/donations/checkout/once', $html);
        $this->assertStringContainsString('/api/donations/checkout/monthly', $html);
        $this->assertStringNotContainsString('mpago.la', $html);
        $this->assertStringNotContainsString('/api/users/donation', $html);
    }
}
