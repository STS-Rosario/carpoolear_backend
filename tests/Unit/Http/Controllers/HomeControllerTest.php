<?php

namespace Tests\Unit\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Http\Controllers\HomeController;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    #[DataProvider('viewMethodProvider')]
    public function test_static_pages_return_expected_view(string $method, string $viewName): void
    {
        $response = app(HomeController::class)->{$method}();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame($viewName, $response->getName());
    }

    public static function viewMethodProvider(): array
    {
        return [
            'privacidad' => ['privacidad', 'privacidad'],
            'terminos' => ['terminos', 'terminos'],
            'acerca de equipo' => ['acercaDeEquipo', 'acerca-de-equipo'],
            'acerca de proyecto' => ['acercaDeProyecto', 'acerca-de-proyecto'],
            'auto rojo' => ['autoRojo', 'auto-rojo'],
            'plataforma faq' => ['plataformaPreguntasFrecuentes', 'plataforma-preguntas-frecuentes'],
            'plataforma recomendaciones' => ['plataformaRecomendaciones', 'plataforma-recomendaciones'],
            'plataforma terminos' => ['plataformaTerminosYCondiciones', 'plataforma-terminos-condiciones'],
            'colabora' => ['colaboraComoColaborar', 'colabora-como-colaborar'],
            'ideame' => ['colaboraIdeame2014', 'colabora-ideame-2014'],
            'difusion' => ['difusion', 'difusion'],
            'mesa de ayuda' => ['mesadeayuda', 'mesadeayuda'],
            'contacto' => ['contacto', 'contacto'],
            'encuentro carpoolero' => ['encuentrocarpoolero', 'encuentrocarpoolero'],
            'donar' => ['donar', 'donar'],
            'covid' => ['covid', 'covid'],
            'freelance' => ['freelance', 'freelance'],
            'derrumbe' => ['derrumbe', 'derrumbe'],
            'lucro' => ['lucro', 'lucro'],
            'donar compartir' => ['donarcompartir', 'donar-compartir'],
            'datos' => ['datos', 'datos'],
            'programar' => ['programar', 'programar'],
        ];
    }

    public function test_home_returns_home_view_when_no_redirection_url(): void
    {
        Config::set('carpoolear.home_redirection', '');

        $response = app(HomeController::class)->home();

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('home', $response->getName());
    }

    public function test_home_redirects_away_when_redirection_url_is_configured(): void
    {
        Config::set('carpoolear.home_redirection', 'https://example.org/welcome');

        $response = app(HomeController::class)->home();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.org/welcome', $response->getTargetUrl());
    }

    public function test_ends_with_handles_empty_and_non_empty_suffixes(): void
    {
        $controller = app(HomeController::class);

        $this->assertTrue($controller->endsWith('bundle.js', '.js'));
        $this->assertFalse($controller->endsWith('bundle.css', '.js'));
        $this->assertTrue($controller->endsWith('anything', ''));
        $this->assertTrue($controller->endsWith('price_99', '99'));
        $this->assertTrue($controller->endsWith('price_99', 99));
        $this->assertFalse($controller->endsWith('abcdef', 'ab'));
    }

    public function test_descarga_redirects_to_app_store_for_ios_user_agent(): void
    {
        $request = Request::create('/descarga', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Mobile/15E148 Safari/604.1',
        ]);

        $response = app(HomeController::class)->descarga($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('itunes.apple.com', $response->headers->get('Location'));
    }

    public function test_descarga_redirects_to_play_store_for_non_ios_user_agent(): void
    {
        $request = Request::create('/descarga', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Mobile Safari/537.36',
        ]);

        $response = app(HomeController::class)->descarga($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('play.google.com', $response->headers->get('Location'));
    }

    public function test_hash_password_returns_plain_text_bcrypt_when_query_param_present(): void
    {
        $request = Request::create('/generateHash', 'GET', ['p' => 'secret-password']);

        $response = app(HomeController::class)->hashPassword($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('$2y$', $response->getContent());
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function test_hash_password_returns_no_content_without_query_param(): void
    {
        $request = Request::create('/generateHash', 'GET');

        $response = app(HomeController::class)->hashPassword($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_handle_app_returns_js_asset_or_index_html(): void
    {
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/app/main.js')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/app/main.js')
            ->andReturn('app-js-content');
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/app/index.html')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/app/index.html')
            ->andReturn('app-index-content');

        $controller = app(HomeController::class);

        $this->assertSame('app-js-content', $controller->handleApp('assets/js/main.js'));
        $this->assertSame('app-index-content', $controller->handleApp('dashboard'));
    }

    public function test_handle_app_returns_404_when_asset_does_not_exist(): void
    {
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/app/DatePicker.C03Rsm5d.js')
            ->andReturn(false);

        $response = app(HomeController::class)->handleApp('DatePicker.C03Rsm5d.js');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_handle_campaigns_and_dev_return_expected_files_for_js_and_index(): void
    {
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/campaigns/vendor.js')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/campaigns/vendor.js')
            ->andReturn('campaigns-js');
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/campaigns/index.html')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/campaigns/index.html')
            ->andReturn('campaigns-index');
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/dev/runtime.js')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/dev/runtime.js')
            ->andReturn('dev-js');
        File::shouldReceive('exists')
            ->once()
            ->with(public_path().'/dev/index.html')
            ->andReturn(true);
        File::shouldReceive('get')
            ->once()
            ->with(public_path().'/dev/index.html')
            ->andReturn('dev-index');

        $controller = app(HomeController::class);

        $this->assertSame('campaigns-js', $controller->handleCampaigns('x/y/vendor.js'));
        $this->assertSame('campaigns-index', $controller->handleCampaigns('landing'));
        $this->assertSame('dev-js', $controller->handleDev('x/runtime.js'));
        $this->assertSame('dev-index', $controller->handleDev('landing'));
    }

    public function test_desuscribirme_calls_user_manager_when_email_is_present(): void
    {
        $request = Request::create('/desuscribirme', 'GET', ['email' => 'user@example.org']);
        $usersManager = $this->createMock(UsersManager::class);
        $usersManager->expects($this->once())
            ->method('mailUnsuscribe')
            ->with('user@example.org');

        $response = app(HomeController::class)->desuscribirme($request, $usersManager);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('unsuscribe', $response->getName());
    }

    public function test_desuscribirme_does_not_call_user_manager_without_email(): void
    {
        $request = Request::create('/desuscribirme', 'GET');
        $usersManager = $this->createMock(UsersManager::class);
        $usersManager->expects($this->never())->method('mailUnsuscribe');

        $response = app(HomeController::class)->desuscribirme($request, $usersManager);

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('unsuscribe', $response->getName());
    }
}
