<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class UnLitroParaCarpoolearPageTest extends TestCase
{
    public function test_page_is_accessible_and_contains_key_sections(): void
    {
        $response = $this->get('/un-litro-para-carpoolear');

        $response->assertOk();
        $response->assertSee('¿Qué es 1 litro para Carpoolear?', false);
        $response->assertSee(
            'Carpoolear es un proyecto colaborativo <strong>sin fines de lucro</strong> de la asociación civil STS Rosario. Ese litro ayuda a mantener la plataforma, la mesa de ayuda y las mejoras.',
            false
        );
        $response->assertSee('Así funciona', false);
        $response->assertSee('Un peaje más, para la comunidad', false);
        $response->assertSee('Se divide entre todas las personas que viajan', false);
        $response->assertSee('Tus 2 primeros viajes son sin cargo', false);
        $response->assertSee('Para probar la plataforma.', false);
        $response->assertSee(
            'Es el valor de 1 litro de nafta, dentro de los gastos compartidos del viaje.',
            false
        );
        $response->assertSee(
            'Quien maneja lo paga al publicar y lo comparte con las demás personas.',
            false
        );
        $response->assertSee(
            'Se cobra en los trayectos más viajados y compartidos.',
            false
        );
        $response->assertSee('1/4 L', false);
        $response->assertSee(
            'Si nadie se sube a tu viaje, se aplica para el siguiente',
            false
        );
        $response->assertSee(
            'es y va a seguir siendo un proyecto sin fines de lucro',
            false
        );
    }

    public function test_plataforma_nav_includes_un_litro_link(): void
    {
        $response = $this->get('/un-litro-para-carpoolear');

        $response->assertOk();
        $response->assertSee('href="un-litro-para-carpoolear"', false);
        $response->assertSee('Un litro para Carpoolear', false);
    }
}
