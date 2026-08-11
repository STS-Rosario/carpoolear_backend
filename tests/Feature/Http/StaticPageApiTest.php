<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class StaticPageApiTest extends TestCase
{
    public function test_faq_static_page_is_public_and_returns_html_content(): void
    {
        $response = $this->get('api/static-pages/faq');

        $response->assertOk();
        $decoded = json_decode($response->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('content', $decoded);
        $this->assertStringContainsString('Preguntas Frecuentes', $decoded['content']);
        $this->assertStringContainsString(
            '<a href="/division-de-gastos">cómo se calcula la contribución por persona para un viaje</a>',
            $decoded['content']
        );
    }

    public function test_division_de_gastos_static_page_returns_html_content(): void
    {
        $response = $this->get('api/static-pages/division-de-gastos');

        $response->assertOk();
        $decoded = json_decode($response->getContent(), true);
        $this->assertStringContainsString(
            '¿Cómo se dividen los gastos en Carpoolear?',
            $decoded['content']
        );
    }

    public function test_verificacion_cuenta_static_page_returns_html_content(): void
    {
        $response = $this->get('api/static-pages/verificacion-cuenta');

        $response->assertOk();
        $decoded = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Verificación de cuenta', $decoded['content']);
    }

    public function test_unknown_static_page_returns_not_found(): void
    {
        $this->get('api/static-pages/unknown-page')->assertNotFound();
    }
}
