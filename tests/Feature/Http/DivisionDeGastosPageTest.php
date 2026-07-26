<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class DivisionDeGastosPageTest extends TestCase
{
    public function test_division_de_gastos_page_is_accessible_and_contains_expected_content(): void
    {
        $response = $this->get('/division-de-gastos');

        $response->assertOk();
        $response->assertSee('¿Cómo se dividen los gastos en Carpoolear?', false);
        $response->assertSee('Sin lucro', false);
        $response->assertSee('Seguro automotor', false);
        $response->assertSee('Cálculo', false);
        $response->assertSee('Contribución por persona', false);
    }
}
