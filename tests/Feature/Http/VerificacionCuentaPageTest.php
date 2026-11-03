<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class VerificacionCuentaPageTest extends TestCase
{
    public function test_verificacion_cuenta_page_is_accessible_and_contains_expected_content(): void
    {
        $response = $this->get('/verificacion-cuenta');

        $response->assertOk();
        $content = $response->getContent();
        $response->assertSee('Verificación de cuenta', false);
        $response->assertDontSee('Version 2', false);
        $response->assertSee('¿Por qué?', false);
        $response->assertSee('+Seguro+Justo+Simple', false);
        $response->assertSee(
            'Una verificación opcional no iba a resolver el tema',
            false
        );
        $response->assertSee('Verificación por Mercado Pago', false);
        $response->assertSee('Verificación manual', false);
        $this->assertLessThan(
            strpos($content, 'Verificación por Mercado Pago'),
            strpos($content, 'Verificación manual')
        );
        $response->assertSee(
            'al vincular la app de Mercado Pago con la app Carpoolear',
            false
        );
        $response->assertSee(
            'gratuito, automático y casi inmediato',
            false
        );
        $response->assertSee(
            'Carpoolear NO obtiene NI obtendremos otros datos',
            false
        );
        $response->assertSee(
            'sin publicidad intrusiva, sin venta de datos',
            false
        );
        $response->assertDontSee(
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
            'Podés desvincular las apps de Mercado Pago',
            false
        );
        $response->assertDontSee(
            'siempre podés usar la verificación manual',
            false
        );
        $response->assertSee('las fotos se borran automáticamente', false);
        $response->assertDontSee('las fotos se borrán automáticamente', false);
        $response->assertSee('¿Cómo hacer el pago con QR?', false);
        $response->assertSee(
            '<a href="https://www.carpoolear.com.ar/app" target="_blank" rel="noopener noreferrer">www.carpoolear.com.ar/app</a> en un navegador',
            false
        );
    }
}
