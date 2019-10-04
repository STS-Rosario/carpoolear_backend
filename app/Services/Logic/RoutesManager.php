<?php

namespace STS\Services\Logic;

use STS\Repository\RoutesRepository as RoutesRep;

use STS\Contracts\Logic\Routes as RoutesLogic;


use Carbon\Carbon;
use STS\Entities\Trip;
use STS\Entities\Route;
use STS\Entities\NodeGeo;


class RoutesManager implements RoutesLogic
{
    protected $routesRepo;

    public function __construct(RoutesRep $routesRepo){
        $this->routesRepo = $routesRepo;
    }

    public function autocomplete($name, $country, $multicountry) {
        return $this->routesRepo->autocomplete($name, $country, $multicountry);
    }


}
