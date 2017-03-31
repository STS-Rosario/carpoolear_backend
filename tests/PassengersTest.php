<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class PassengersTest extends TestCase
{
    use DatabaseTransactions;

    protected $passengerManager;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        $this->passengerManager = \App::make('\STS\Contracts\Logic\IPassengersLogic');
    }

    //TODO: Hacer los Test de la logica de pasajeros
}
