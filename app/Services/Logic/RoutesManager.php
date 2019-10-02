<?php

namespace STS\Services\Logic;
use STS\Contracts\Repository\RoutesRepository as RoutesRep;
use STS\Contracts\Logic\Routes as RoutesLogic;


use Carbon\Carbon;
use STS\Entities\Trip;
use STS\Entities\Route;
use STS\Entities\NodeGeo;


class RoutesManager implements RoutesLogic
{
    public function __construct(RoutesRep $routesRepo){
        $this->$routesRepo = $routesRepo;
    }


}
