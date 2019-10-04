<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Entities\Route;
use STS\Entities\NodeGeo;

use STS\Contracts\Repository\Routes as RoutesRep;


class RoutesRepository implements RoutesRep
{
    public function __construct () {
    }
    // Ecuacion de la circunferencia: (x - cx)^2 + (y - cy)^2 = r^2 
    // $expresion = "(POW(lat - $cx, 2) + POW(lng - $cy, 2)) = POW($radius, 2)";
    public function getPotentialsNodes ($n1, $n2) {
        $maxLat = 0;
        $minLat = 0;
        $minLng = 0;
        $maxLng = 0;
        if ($n1->lat > $n2->lat) {
            $maxLat = $n1->lat;
            $minLat = $n2->lat;
        } else {
            $maxLat = $n2->lat;
            $minLat = $n1->lat;
        }
        if ($n1->lng > $n2->lng) {
            $maxLng = $n1->lng;
            $minLng = $n2->lng;
        } else {
            $maxLng = $n2->lng;
            $minLng = $n1->lng;
        }
        $latDiff = 0.5;
        $query = NodeGeo::whereBetween('lat', [$minLat - $latDiff, $maxLat + $latDiff]);
        // 1 / (cos(lat) * 110)
        // FIXME
        $lngDiff = 2;
        $query->whereBetween('lng', [$minLng - $lngDiff, $maxLng + $lngDiff]);
    }
    public function autocomplete($name, $country, $multicountry) 
    {
        $query = NodeGeo::query();
        $query->where(function ($q) use ($name) {
            $q->where('name', 'like', '%'.$name.'%');
        });
        if(!$multicountry) {
            $query->where('country',$country);
        }
        $query->orderBy('importance', 'DESC');
        $query->limit(5);
        return $query->get();
    }
}

