<?php

namespace STS\Http\Controllers\Api\v1;

use DB;
use STS\Http\Controllers\Controller;
use Illuminate\Http\Request;
use STS\Contracts\Logic\User as UserLogic;
use STS\Entities\Rating as RatingModel;
use Carbon\Carbon;

class DataController extends Controller
{
    public function trips() {
        $queryViajes = "
            SELECT DATE_FORMAT(trip_date, '%Y-%m') AS 'key', 
                    DATE_FORMAT(trip_date, '%Y') AS 'año', 
                    DATE_FORMAT(trip_date, '%m') AS 'mes',
                    count(*) AS 'cantidad',
                    sum(total_seats) AS 'asientos_ofrecidos_total'
                FROM trips
            WHERE is_passenger = 0
            GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
        ";
        $viajes = DB::select($queryViajes, array());

        return response()->json([
            'trips' => $viajes
        ]);


    }

    public function seats() {
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
        $asientos = DB::select($querySolicitudes, array());

        return response()->json([
            'seats' => $asientos
        ]);


    }
    
    public function users() {
        $queryUsuarios = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS 'key', 
            DATE_FORMAT(created_at, '%Y') AS 'año', 
            DATE_FORMAT(created_at, '%m') AS 'mes',
            count(*) AS 'cantidad'
        FROM users
        WHERE created_at IS NOT NULL
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ";
        $usuarios = DB::select($queryUsuarios, array());

        return response()->json([
            'users' => $usuarios
        ]);
    }

    public function data () {

        $queryOrigenesFrecuencia = "
            SELECT MIN(tp.address) 'origen', tp.lat, tp.lng, count(*) AS 'cantidad'
            FROM trips t
            INNER JOIN (SELECT trip_id, MIN(id) AS 'origen' FROM trips_points GROUP BY trip_id) origenes ON t.id = origenes.trip_id
            INNER JOIN trips_points tp ON tp.id = origenes.origen
            GROUP BY tp.lat, tp.lng
            ORDER BY count(*) DESC
        ";
        
        $frecuencia_origenes_posterior_ago_2017 = DB::select($queryOrigenesFrecuencia, array());

        $frecuencia_origenes_posterior_ago_2017 = array_slice($frecuencia_origenes_posterior_ago_2017, 0, 25);

        $queryDestinosFrecuencia = "
            SELECT MIN(tp.address) 'destino', tp.lat, tp.lng, count(*) AS 'cantidad'
            FROM trips t
            INNER JOIN (SELECT trip_id, MAX(id) AS 'destino' FROM trips_points GROUP BY trip_id) destinos ON t.id = destinos.trip_id
            INNER JOIN trips_points tp ON tp.id = destinos.destino
            GROUP BY tp.lat, tp.lng
            ORDER BY count(*) DESC
        ";
        $frecuencia_destinos_posterior_ago_2017 = DB::select($queryDestinosFrecuencia, array());

        $frecuencia_destinos_posterior_ago_2017 = array_slice($frecuencia_destinos_posterior_ago_2017, 0, 25);

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
        $frecuencia_origenes_destinos_posterior_ago_2017 = DB::select($queryOrigenesDestinosFrecuencia, array());

        $frecuencia_origenes_destinos_posterior_ago_2017 = array_slice($frecuencia_origenes_destinos_posterior_ago_2017, 0, 25);


        return response()->json([
            'usuarios' => $usuarios,
            'viajes' => $viajes,
            'solicitudes' => $solicitudes,
            'frecuencia_origenes_posterior_ago_2017' => $frecuencia_origenes_posterior_ago_2017,
            'frecuencia_destinos_posterior_ago_2017' => $frecuencia_destinos_posterior_ago_2017,
            'frecuencia_origenes_destinos_posterior_ago_2017' => $frecuencia_origenes_destinos_posterior_ago_2017
        ]);
    }

    public function moreData () {
        $queryCalificaciones = "
            SELECT r.user_id_to, u.name, COUNT(*) as rating
                FROM rating r
                    INNER JOIN users u ON r.user_id_to = u.id
                WHERE voted = 1
                GROUP BY r.user_id_to, u.name
                ORDER BY COUNT(*) DESC
            LIMIT 50;
        ";
        $calificaciones = DB::select($queryCalificaciones, array());


        $queryViajesConductores = "
            SELECT t.user_id, u.name, COUNT(*) as drives
                FROM trips t
                    INNER JOIN users u ON t.user_id = u.id
                WHERE t.is_passenger = 0
                GROUP BY t.user_id, u.name
                ORDER BY COUNT(*) DESC
            LIMIT 50;
        ";
        $conductores = DB::select($queryViajesConductores, array());



        $queryViajesPasajeros = "
            SELECT tp.user_id, u.name, count(*) as drives
            FROM trip_passengers tp
            INNER JOIN users u ON tp.user_id = u.id
            WHERE tp.request_state = 1
            GROUP BY tp.user_id, u.name
            ORDER BY COUNT(*) DESC
            LIMIT 50;

        ";
        $pasajeros = DB::select($queryViajesPasajeros, array());

        return response()->json([
            'ranking_calificaciones' => $calificaciones,
            'ranking_conductores' => $conductores,
            'ranking_pasajeros' => $pasajeros
        ]);
    }


}
