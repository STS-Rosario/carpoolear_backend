<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaticPageNavigationTest extends TestCase
{
    #[DataProvider('staticPageProvider')]
    public function test_static_page_navigation_does_not_include_empresas_link(string $path): void
    {
        Config::set('carpoolear.home_redirection', '');

        $response = $this->get($path);

        $response->assertOk();
        $this->assertNavigationExcludesEmpresasLink($response);
        $response->assertSee('href="contacto"', false);
    }

    private function assertNavigationExcludesEmpresasLink(TestResponse $response): void
    {
        $response->assertDontSee('>Empresas<', false);
        $response->assertDontSee('carpoolearmas.com.ar', false);
    }

    public static function staticPageProvider(): array
    {
        return [
            'home' => ['/home'],
            'contacto' => ['/contacto'],
            'acerca de proyecto' => ['/acerca-de-proyecto'],
        ];
    }
}
