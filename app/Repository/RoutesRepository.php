<?php

namespace STS\Repository;

use STS\Models\NodeGeo;


class RoutesRepository
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
        return $query->get();
    }
    public function autocomplete($name, $country, $multicountry) 
    {
        \Log::info($name. ' ' . $country);
        //sometime someone will implement full text search
        $query = NodeGeo::query();
        $query->whereRaw("CONCAT(name, ' ', state, ' ', country) like ?", '%'.$name.'%');

        if(!$multicountry) {
            $query->where('country',$country);
        }

        $query->orderBy('importance', 'DESC');
        $query->limit(5);

        return $query->get();
    }

    public function saveRoute ($route, $points) {
        $nodeIds = [];
        foreach ($points as $node) {   
            $nodeIds[] = $node->id;
        }
        // sync remueve los nodos iniciales
        $route->nodes()->sync($nodeIds);
        $route->processed = true;
        $route->save();
    }
}

