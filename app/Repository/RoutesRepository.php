<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Entities\Route;
use STS\Entities\NodeGeo;

use STS\Contracts\Repository\Routes as RoutesRep;


class RoutesRepository implements RoutesRep
{
    public function autocomplete($name, $country, $multicountry) 
    {
        $query = NodeGeo::query();
        $query->where(function ($q) use ($name) {
            $q->where('name', 'like', '%'.$name.'%');
        });
        \Log::info(var_export($multicountry, true));
        if(!$multicountry) {
            \Log::info("aaaaa");
            $query->where('country',$country);
        }
        $query->orderBy('importance', 'DESC');
        $query->limit(5);
        return $query->get();
    }
}

