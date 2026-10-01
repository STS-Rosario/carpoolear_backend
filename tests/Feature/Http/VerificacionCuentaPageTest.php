<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class VerificacionCuentaPageTest extends TestCase
{
    public function test_verificacion_cuenta_page_is_accessible_and_contains_expected_content(): void
    {
        $response = $this->get('/verificacion-cuenta');

        $response->assertOk();
        $response->assertSee('Verificación de cuenta', false);
        $response->assertSee('¿Por qué?', false);
        $response->assertSee('Verificación por Mercado Pago', false);
        $response->assertSee('Verificación manual', false);
        $response->assertSee(
            'es un tema de Mercado Pago ese otorgamiento',
            false
        );
        $response->assertSee(
            '<a href="https://www.mercadopago.com.ar/accounts/my-apps" target="_blank" rel="noopener noreferrer">eliminar la integración con Mercado Pago</a>',
            false
        );
        $response->assertSee(
            'Aplicaciones conectadas -> Carpoolear-> Quitar permisos',
            false
        );
        $response->assertSee(
            'siempre podés usar la verificación manual',
            false
        );
        $response->assertSee('las fotos se borran automáticamente', false);
        $response->assertDontSee('las fotos se borrán automáticamente', false);
        $response->assertSee('¿Cómo hacer el pago con QR?', false);
        $response->assertSee(
            '<a href="https://www.carpoolear.com.ar/app" target="_blank" rel="noopener noreferrer">www.carpoolear.com.ar/app</a>',
            false
        );
    }
}
