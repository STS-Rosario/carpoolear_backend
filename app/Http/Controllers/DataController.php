<?php

namespace STS\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use STS\Contracts\Logic\User as UserLogic;
use STS\Entities\Rating as RatingModel;
use Carbon\Carbon;

class DataController extends Controller
{
    public function data () {
        $queryViajes = "
            SELECT DATE_FORMAT(trip_date, '%Y-%m') AS 'key', 
                    DATE_FORMAT(trip_date, '%Y') AS 'año', 
                    DATE_FORMAT(trip_date, '%m') AS 'mes',
                    count(*) AS 'cantidad',
                    avg(total_seats) AS 'asientos_ofrecidos_promedio'
                FROM trips
            WHERE is_passenger = 0
            GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
        ";
        $viajes = DB::select(DB::raw($queryViajes), array());

        $querySolicitudes = "
            SELECT DATE_FORMAT(trip_date, '%Y-%m') as 'key', 
                DATE_FORMAT(trip_date, '%Y') AS 'año', 
                DATE_FORMAT(trip_date, '%m') AS 'mes',
                tp.request_state AS 'state',
                CASE 
                    WHEN tp.request_state = 0 THEN 'pendiente'
                    WHEN tp.request_state = 1 THEN 'aceptada'
                    WHEN tp.request_state = 2 THEN 'rechazada_conductor'
                    WHEN tp.request_state = 3 THEN 'retirada_pasajero'
                END AS 'estado',
                count(*) as 'cantidad'
                FROM trips t
                INNER JOIN trip_passengers tp on t.id = tp.trip_id
                WHERE t.is_passenger = 0
                GROUP BY DATE_FORMAT(trip_date, '%Y-%m'), tp.request_state
        ";
        $solicitudes = DB::select(DB::raw($querySolicitudes), array());


        $queryOrigenesFrecuencia = "
            SELECT MIN(tp.address) 'origen', tp.lat, tp.lng, count(*) AS 'cantidad'
            FROM trips t
            INNER JOIN (SELECT trip_id, MIN(id) AS 'origen' FROM trips_points GROUP BY trip_id) origenes ON t.id = origenes.trip_id
            INNER JOIN trips_points tp ON tp.id = origenes.origen
            GROUP BY tp.lat, tp.lng
            ORDER BY count(*) DESC
        ";
        
        $frecuencia_origenes_posterior_ago_2017 = DB::select(DB::raw($queryOrigenesFrecuencia), array());

        $queryDestinosFrecuencia = "
            SELECT MIN(tp.address) 'destino', tp.lat, tp.lng, count(*) AS 'cantidad'
            FROM trips t
            INNER JOIN (SELECT trip_id, MAX(id) AS 'destino' FROM trips_points GROUP BY trip_id) destinos ON t.id = destinos.trip_id
            INNER JOIN trips_points tp ON tp.id = destinos.destino
            GROUP BY tp.lat, tp.lng
            ORDER BY count(*) DESC
        ";
        $frecuencia_destinos_posterior_ago_2017 = DB::select(DB::raw($queryDestinosFrecuencia), array());

        $queryOrigenesDestinosFrecuencia = "
            SELECT MIN(tpo.address) 'origen', tpo.lat 'o_lat', tpo.lng 'o_lng', MIN(tpd.address) 'destino', tpd.lng 'd_lat',tpd.lat 'd_lng', count(*) AS 'cantidad'
            FROM trips t
            INNER JOIN (SELECT trip_id, MIN(id) AS 'origen' FROM trips_points GROUP BY trip_id) origenes ON t.id = origenes.trip_id
            INNER JOIN (SELECT trip_id, MAX(id) AS 'destino' FROM trips_points GROUP BY trip_id) destinos ON t.id = destinos.trip_id
            INNER JOIN trips_points tpo ON tpo.id = origenes.origen
            INNER JOIN trips_points tpd ON tpd.id = destinos.destino
            GROUP BY tpo.lat, tpo.lng, tpd.lat, tpo.lng
            ORDER BY count(*) DESC
        ";
        $frecuencia_origenes_destinos_posterior_ago_2017 = DB::select(DB::raw($queryOrigenesDestinosFrecuencia), array());

        return $this->response->withArray([
            'viajes' => $viajes,
            'solicitudes' => $solicitudes,
            'frecuencia_origenes_posterior_ago_2017' => $frecuencia_origenes_posterior_ago_2017,
            'frecuencia_destinos_posterior_ago_2017' => $frecuencia_destinos_posterior_ago_2017,
            'frecuencia_origenes_destinos_posterior_ago_2017' => $frecuencia_origenes_destinos_posterior_ago_2017
        ]);
    }


}
