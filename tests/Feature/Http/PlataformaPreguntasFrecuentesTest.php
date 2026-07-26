<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class PlataformaPreguntasFrecuentesTest extends TestCase
{
    public function test_contribucion_faq_links_to_division_de_gastos_page(): void
    {
        $response = $this->get('/plataforma-preguntas-frecuentes');

        $response->assertOk();
        $response->assertSee('¿Cómo se calcula la contribución para un viaje?', false);
        $response->assertSee(
            'En este artículo te explicamos <a href="/division-de-gastos">cómo se calcula la contribución por persona para un viaje</a>.',
            false
        );
    }

    public function test_verificacion_cuenta_faq_links_to_verificacion_cuenta_page(): void
    {
        $response = $this->get('/plataforma-preguntas-frecuentes');

        $response->assertOk();
        $response->assertSee('¿Las cuentas de los usuarios son verificadas?', false);
        $response->assertSee(
            'En este artículo te explicamos <a href="/verificacion-cuenta">cómo funciona la verificación de cuenta en Carpoolear</a>.',
            false
        );
    }

    public function test_faq_does_not_include_removed_sections(): void
    {
        $response = $this->get('/plataforma-preguntas-frecuentes');

        $response->assertOk();
        $response->assertDontSee('¿Quiénes ven los viajes que publicó?: Visibilidad personalizada de viajes.', false);
        $response->assertDontSee('Más informaciòn sobre la regla de contribuciòn màxima', false);
        $response->assertDontSee('table-visibilidad', false);
    }
}
