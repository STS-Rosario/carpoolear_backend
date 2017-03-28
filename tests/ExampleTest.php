<?php

use STS\User;
use STS\Entities\Trip;
use STS\Entities\TripPoint;

use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;

use Illuminate\Foundation\Testing\DatabaseTransactions;

use STS\Notifications\DummyNotification;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testBasicExample()
    {
        $this->visit('/')
             ->see('Laravel 5');
    }
}
