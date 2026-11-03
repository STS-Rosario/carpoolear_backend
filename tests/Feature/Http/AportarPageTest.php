<?php

namespace Tests\Feature\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AportarPageTest extends TestCase
{
    #[DataProvider('publicDonationPaths')]
    public function test_public_donation_page_shows_in_app_copy_without_redirecting(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('Tu aporte', $html);
        $this->assertStringContainsString('es fundamental', $html);
        $this->assertStringContainsString('Quiero formar parte del Club Carpoolear', $html);
        $this->assertStringContainsString('Quiero aportar por única vez', $html);
        $this->assertStringContainsString('/api/donations/checkout/once', $html);
        $this->assertStringContainsString('/api/donations/checkout/qr-order', $html);
        $this->assertStringContainsString('source', $html);
        $this->assertStringContainsString('aportar', $html);
        $this->assertStringNotContainsString('mpago.la', $html);
        $this->assertStringNotContainsString('/api/users/donation', $html);
        $this->assertStringNotContainsString('¡Hola Carpooler@s!', $html);
    }

    #[DataProvider('publicDonationPaths')]
    public function test_public_donation_page_uses_in_app_typefaces(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('family=Barlow', $html);
        $this->assertStringContainsString('family=Dela+Gothic+One', $html);
        $this->assertMatchesRegularExpression(
            '/\.public-donate__title-line[^{]*\{[^}]*font-family:\s*[\'"]Dela Gothic One[\'"]/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/\.public-donate[^{]*\{[^}]*font-family:\s*[\'"]Barlow[\'"]/',
            $html
        );
        $this->assertStringContainsString('font-size: 16px', $html);
    }

    #[DataProvider('publicDonationPaths')]
    public function test_club_carpoolear_asks_guests_to_log_in_inside_the_app(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString(
            'Para ser miembro del Club Carpoolear tenés que estar logueado en la app, te vamos a redirigir',
            $html
        );
        $this->assertStringContainsString('data-club-login-ok', $html);
        $this->assertStringContainsString('/app/donate', $html);
        $this->assertStringNotContainsString('/api/donations/checkout/monthly', $html);
    }

    public function test_legacy_donar_still_redirects_to_aportar(): void
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

    public static function publicDonationPaths(): array
    {
        return [
            'donate' => ['/donate'],
            'aportar' => ['/aportar'],
        ];
    }
}
