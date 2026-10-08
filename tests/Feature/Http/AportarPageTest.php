<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class AportarPageTest extends TestCase
{
    public function test_sts_link_uses_absolute_https_url(): void
    {
        $response = $this->get('/aportar');

        $response->assertOk();
        $response->assertSee('href="https://www.stsrosario.org.ar"', false);
        $response->assertDontSee('href="www.stsrosario.org.ar"', false);
    }

    public function test_aportar_uses_tracked_checkout_and_cafe_beer_food_tiers(): void
    {
        $html = $this->get('/aportar')->assertOk()->getContent();

        $this->assertStringContainsString('/api/donation-tiers', $html);
        $this->assertStringContainsString('/api/donations/checkout/once', $html);
        $this->assertStringContainsString('/api/donations/checkout/monthly', $html);
        $this->assertMatchesRegularExpression("/source['\":\\s]+aportar/", $html);
        $this->assertStringContainsString('user_id', $html);
        $this->assertStringNotContainsString('mpago.la', $html);
        $this->assertStringNotContainsString('/api/users/donation', $html);
        $this->assertStringNotContainsString('Elegí tu propia aventura', $html);
        $this->assertStringNotContainsString('value="2000"', $html);
        $this->assertStringNotContainsString('value="10000"', $html);
    }

    public function test_aportar_offers_qr_checkout_for_one_time_payments(): void
    {
        $html = $this->get('/aportar')->assertOk()->getContent();

        $this->assertStringContainsString('Pagar con QR', $html);
        $this->assertStringContainsString('/api/donations/checkout/qr-order', $html);
        $this->assertStringContainsString('/api/donations/payments/', $html);
        $this->assertStringContainsString('platform_donations_qr_enabled', $html);
        $this->assertStringContainsString('qr-payment-panel', $html);
        $this->assertStringContainsString('Escanéa con una billetera virtual', $html);
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
