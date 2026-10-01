<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandingFaviconTest extends TestCase
{
    #[DataProvider('landingPageProvider')]
    public function test_landing_page_links_carpoolear_favicon(string $path): void
    {
        Config::set('carpoolear.home_redirection', '');

        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/png" href="'.asset('img/carpoolear_logo_square.png').'">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="'.asset('img/icon-1024.png').'">', false);
    }

    public function test_favicon_files_exist_in_public(): void
    {
        $this->assertFileExists(public_path('img/carpoolear_logo_square.png'));
        $this->assertFileExists(public_path('img/icon-1024.png'));
    }

    public static function landingPageProvider(): array
    {
        return [
            'root' => ['/'],
            'home' => ['/home'],
            'auto rojo' => ['/autorojo'],
            'aportar' => ['/aportar'],
        ];
    }
}
