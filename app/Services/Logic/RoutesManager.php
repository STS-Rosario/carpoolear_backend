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
    public function __construct(RoutesRep $routesRepo){
        $this->routesRepo = $routesRepo;
    }

    private function distance ($lat1, $lon1, $lat2, $lon2) {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        }
        else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;
            return $miles * 1.609344; // kms
        }
    }
    /*
        // Centro de la circunferencia
        // cx = (Xa + Xb) / 2
        // cy = (Ya + Yb) / 2
        $cx = ($sourceNode->lat + $destinyNode->lat) / 2;
        $cy = ($sourceNode->lng + $destinyNode->lng) / 2;
        // r = distancia ente ambos puntos
        $r = $this->distance($sourceNode->lat, $sourceNode->lng, $destinyNode->lat, $destinyNode->lng);
        // Ecuacion de la circunferencia: (x - cx)^2 + (y - cy)^2 = r^2 
    */
    public function createRoute ($sourceNode, $destinyNode) {
        // 1- Obenter todos los nodos geo dentro de la circunferencia
        $nodes = $this->routesRepo->getPotentialsNodes($sourceNode, $destinyNode);
        // 2- Calcular con OSM la ruta
        echo "[";
        foreach ($nodes as $node) {
            echo "['', $node->lng, $node->lat, 1],";
        }
        echo "]";die;
        // 3- Buscar los puntos obtenidos en 1 cercanos a los segumentos
        // 4- Grabar
    }
}
