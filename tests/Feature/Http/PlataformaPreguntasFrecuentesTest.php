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
}
