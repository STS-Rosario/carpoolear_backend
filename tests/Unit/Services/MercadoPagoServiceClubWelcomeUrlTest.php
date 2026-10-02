<?php

namespace Tests\Unit\Services;

use InvalidArgumentException;
use STS\Services\MercadoPagoService;
use Tests\TestCase;

class MercadoPagoServiceClubWelcomeUrlTest extends TestCase
{
    public function test_club_carpoolear_welcome_return_url_follows_app_path_format(): void
    {
        config(['carpoolear.frontend_url' => 'https://frontend.test']);

        $service = new MercadoPagoService;

        $this->assertSame(
            'https://frontend.test/app/club-carpoolear/welcome?result=success',
            $service->clubCarpoolearWelcomeReturnUrl('success')
        );
        $this->assertSame(
            'https://frontend.test/app/club-carpoolear/welcome?result=failed',
            $service->clubCarpoolearWelcomeReturnUrl('failed')
        );
        $this->assertSame(
            'https://frontend.test/app/club-carpoolear/welcome?result=pending',
            $service->clubCarpoolearWelcomeReturnUrl('pending')
        );
    }

    public function test_club_carpoolear_welcome_return_url_trims_trailing_slash_on_frontend_url(): void
    {
        config(['carpoolear.frontend_url' => 'https://frontend.test/']);

        $service = new MercadoPagoService;

        $this->assertSame(
            'https://frontend.test/app/club-carpoolear/welcome?result=success',
            $service->clubCarpoolearWelcomeReturnUrl('success')
        );
    }

    public function test_club_carpoolear_welcome_return_url_throws_when_frontend_url_missing(): void
    {
        config(['carpoolear.frontend_url' => '']);

        $service = new MercadoPagoService;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carpoolear.frontend_url must be set for Club Carpoolear welcome return URL');

        $service->clubCarpoolearWelcomeReturnUrl('success');
    }
}
