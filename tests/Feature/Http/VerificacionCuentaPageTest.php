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
    }
}
