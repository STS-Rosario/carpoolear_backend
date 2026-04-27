<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use STS\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class VerifyCsrfTokenTest extends TestCase
{
    private function middleware(): VerifyCsrfToken
    {
        return new VerifyCsrfToken($this->app, $this->app->make('encrypter'));
    }

    public function test_excluded_paths_include_payment_and_webhook_routes(): void
    {
        $paths = $this->middleware()->getExcludedPaths();

        $this->assertContains('transbank-respuesta', $paths);
        $this->assertContains('transbank-final', $paths);
        $this->assertContains('webhooks/mercadopago', $paths);
    }

    #[DataProvider('exceptedPathProvider')]
    public function test_post_requests_to_excepted_paths_skip_csrf_gate(string $path): void
    {
        $middleware = $this->middleware();
        $method = new ReflectionMethod(VerifyCsrfToken::class, 'inExceptArray');
        $method->setAccessible(true);

        $request = Request::create($path, 'POST');

        $this->assertTrue($method->invoke($middleware, $request), "Expected {$path} to match CSRF except list");
    }

    public static function exceptedPathProvider(): array
    {
        return [
            'transbank respuesta' => ['/transbank-respuesta'],
            'transbank final' => ['/transbank-final'],
            'mercadopago webhook' => ['/webhooks/mercadopago'],
        ];
    }

    public function test_non_excluded_post_path_is_not_in_except_array(): void
    {
        $middleware = $this->middleware();
        $method = new ReflectionMethod(VerifyCsrfToken::class, 'inExceptArray');
        $method->setAccessible(true);

        $request = Request::create('/api/trips', 'POST');

        $this->assertFalse($method->invoke($middleware, $request));
    }
}
