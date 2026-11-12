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
        $response->assertSee(
            'Mantener en funcionamiento el sitio y la app para que sigas encontrando y compartiendo viajes.',
            false
        );
        $response->assertSee(
            'Responder las consultas y resolver los problemas de quienes usan Carpoolear.',
            false
        );
        $response->assertSee(
            'Desarrollar nuevas funcionalidades y mejorar las que ya existen.',
            false
        );
        $response->assertSee('Se divide entre todas las personas que viajan', false);
        $response->assertSee(
            'Quien maneja lo paga al publicar y lo comparte, como la nafta y los peajes.',
            false
        );
        $response->assertSee(
            'Es decir: el litro se adelanta al crear el viaje y después se divide entre todas las personas que viajan, conductor incluido.',
            false
        );
        $response->assertSee('* Ejemplo con 4 personas viajando.', false);
        $response->assertSee('un-litro__seats-explainer', false);
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
        $response->assertSee('¼ L', false);
        $response->assertSee('un-litro__seat--driver', false);
        $response->assertSee('un-litro__seat-meta', false);
        $response->assertSee('un-litro__seat-label', false);
        $response->assertSee('background: #F4F3F1', false);
        $response->assertSee('background: #E7F1FB', false);
        $response->assertSee('border: 1.5px solid #93B3D0', false);
        $response->assertSee(
            'Si nadie se sube a tu viaje, se aplica para el siguiente',
            false
        );
        $response->assertSee(
            'Si nadie reservó un lugar, no tenés que volver a pagarlo: el litro se traslada directo a tu siguiente viaje publicado.',
            false
        );
        $response->assertSee('No se suma ningún litro.', false);
        $response->assertSee('Tampoco se suma ningún litro.', false);
        $response->assertSee(
            'Desde acá se suma el equivalente a 1 litro de nafta.',
            false
        );
        $response->assertSee('color: #8B2129', false);
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
