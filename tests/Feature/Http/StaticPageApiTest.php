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
        $this->assertStringContainsString(
            '<a href="/un-litro-para-carpoolear">más detalles sobre Un litro para Carpoolear acá</a>',
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
        $this->assertStringNotContainsString('Version 2', $decoded['content']);
        $this->assertStringContainsString('+Seguro+Justo+Simple', $decoded['content']);
        $this->assertStringContainsString(
            'Una verificación opcional no iba a resolver el tema',
            $decoded['content']
        );
        $this->assertLessThan(
            strpos($decoded['content'], 'Verificación por Mercado Pago'),
            strpos($decoded['content'], 'Verificación manual')
        );
        $this->assertStringContainsString(
            'al vincular la app de Mercado Pago con la app Carpoolear',
            $decoded['content']
        );
        $this->assertStringContainsString(
            'Carpoolear NO obtiene NI obtendremos otros datos',
            $decoded['content']
        );
        $this->assertStringNotContainsString(
            'es un tema de Mercado Pago ese otorgamiento',
            $decoded['content']
        );
        $this->assertStringContainsString(
            'eliminar la integración con Mercado Pago',
            $decoded['content']
        );
        $this->assertStringContainsString(
            '¿Cómo hacer el pago con QR?',
            $decoded['content']
        );
        $this->assertStringContainsString(
            'www.carpoolear.com.ar/app',
            $decoded['content']
        );
        $this->assertStringContainsString(
            'en un navegador',
            $decoded['content']
        );
        $this->assertStringContainsString(
            'las fotos se borran automáticamente',
            $decoded['content']
        );
    }

    public function test_unknown_static_page_returns_not_found(): void
    {
        $this->get('api/static-pages/unknown-page')->assertNotFound();
    }
}
