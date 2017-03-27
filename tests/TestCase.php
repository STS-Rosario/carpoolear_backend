<?php

use Mockery as m;

class TestCase extends Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function mock($class)
    {
        $mock = m::mock($class);
        $this->app->instance($class, $mock);

        return $mock;
    }

    protected function actingAsApiUser($user)
    {
        $this->app['api.auth']->setUser($user);

        return $this;
    }
}
