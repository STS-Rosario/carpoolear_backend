<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class AportarPageTest extends TestCase
{
    public function test_sts_link_uses_absolute_https_url(): void
    {
        $response = $this->get('/aportar');

        $response->assertOk();
        $response->assertSee('href="https://www.stsrosario.org.ar"', false);
        $response->assertDontSee('href="www.stsrosario.org.ar"', false);
    }
}
