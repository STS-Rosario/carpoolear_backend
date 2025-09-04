<?php

namespace STS\Http\Controllers\Api\v1;

use DB;
use Exception;
use STS\Http\Controllers\Controller;
use STS\Models\ActiveUsersPerMonth;

class DataController extends Controller
{
    private const LIMIT_TOP = 25;
    private const LIMIT_RANKING = 50;

    public function trips() 
    {
        try {
            $queryViajes = "
                SELECT DATE_FORMAT(trip_date, '%Y-%m') AS 'key', 
                       DATE_FORMAT(trip_date, '%Y') AS 'año', 
                       DATE_FORMAT(trip_date, '%m') AS 'mes',
                       COUNT(*) AS 'cantidad',
                       SUM(total_seats) AS 'asientos_ofrecidos_total'
                FROM trips
                WHERE is_passenger = 0
                GROUP BY DATE_FORMAT(trip_date, '%Y-%m')
            ";
            
            $viajes = DB::select($queryViajes);
            return response()->json(['trips' => $viajes]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving trips data'], 500);
        }
    }

    public function seats() 
    {
        try {
            $querySolicitudes = "
                SELECT DATE_FORMAT(t.trip_date, '%Y-%m') as 'key', 
                       DATE_FORMAT(t.trip_date, '%Y') AS 'año', 
                       DATE_FORMAT(t.trip_date, '%m') AS 'mes',
                       tp.request_state AS 'state',
                       CASE 
                           WHEN tp.request_state = 0 THEN 'pendiente'
                           WHEN tp.request_state = 1 THEN 'aceptada'
                           WHEN tp.request_state = 2 THEN 'rechazada_conductor'
                           WHEN tp.request_state = 3 THEN 'retirada_pasajero'
                       END AS 'estado',
                       COUNT(*) as 'cantidad'
                FROM trips t
                INNER JOIN trip_passengers tp ON t.id = tp.trip_id
                WHERE t.is_passenger = 0
                GROUP BY DATE_FORMAT(t.trip_date, '%Y-%m'), tp.request_state
            ";
            
            $asientos = DB::select($querySolicitudes);
            return response()->json(['seats' => $asientos]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving seats data'], 500);
        }
    }
    
    public function users() 
    {
        try {
            $queryUsuarios = "
                SELECT DATE_FORMAT(created_at, '%Y-%m') AS 'key', 
                       DATE_FORMAT(created_at, '%Y') AS 'año', 
                       DATE_FORMAT(created_at, '%m') AS 'mes',
                       COUNT(*) AS 'cantidad'
                FROM users
                WHERE created_at IS NOT NULL
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ";
            
            $usuarios = DB::select($queryUsuarios);
            return response()->json(['users' => $usuarios]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving users data'], 500);
        }
    }

    public function monthlyUsers() 
    {
        try {
            // Get active users per month data
            $activeUsersPerMonth = ActiveUsersPerMonth::orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'key' => sprintf('%04d-%02d', $item->year, $item->month),
                        'año' => $item->year,
                        'mes' => $item->month,
                        'cantidad' => $item->value,
                        'saved_at' => $item->saved_at
                    ];
                });
            
            return response()->json(['monthly_users' => $activeUsersPerMonth]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving monthly users data'], 500);
        }
    }

    public function data() 
    {
        try {
            // Get trips data
            $viajes = $this->trips()->original['trips'];
            
            // Get seats data
            $solicitudes = $this->seats()->original['seats'];
            
            // Get users data
            $usuarios = $this->users()->original['users'];

            $queryOrigenesFrecuencia = "
                SELECT MIN(tp.address) 'origen', tp.lat, tp.lng, COUNT(*) AS 'cantidad'
                FROM trips t
                INNER JOIN (
                    SELECT trip_id, MIN(id) AS 'origen' 
                    FROM trips_points 
                    GROUP BY trip_id
                ) origenes ON t.id = origenes.trip_id
                INNER JOIN trips_points tp ON tp.id = origenes.origen
                GROUP BY tp.lat, tp.lng
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $frecuencia_origenes = DB::select($queryOrigenesFrecuencia, [self::LIMIT_TOP]);

            $queryDestinosFrecuencia = "
                SELECT MIN(tp.address) 'destino', tp.lat, tp.lng, COUNT(*) AS 'cantidad'
                FROM trips t
                INNER JOIN (
                    SELECT trip_id, MAX(id) AS 'destino' 
                    FROM trips_points 
                    GROUP BY trip_id
                ) destinos ON t.id = destinos.trip_id
                INNER JOIN trips_points tp ON tp.id = destinos.destino
                GROUP BY tp.lat, tp.lng
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $frecuencia_destinos = DB::select($queryDestinosFrecuencia, [self::LIMIT_TOP]);

            $queryOrigenesDestinosFrecuencia = "
                SELECT MIN(tpo.address) 'origen', 
                       tpo.lat 'o_lat', 
                       tpo.lng 'o_lng', 
                       MIN(tpd.address) 'destino', 
                       tpd.lat 'd_lat',
                       tpd.lng 'd_lng', 
                       COUNT(*) AS 'cantidad'
                FROM trips t
                INNER JOIN (
                    SELECT trip_id, MIN(id) AS 'origen' 
                    FROM trips_points 
                    GROUP BY trip_id
                ) origenes ON t.id = origenes.trip_id
                INNER JOIN (
                    SELECT trip_id, MAX(id) AS 'destino' 
                    FROM trips_points 
                    GROUP BY trip_id
                ) destinos ON t.id = destinos.trip_id
                INNER JOIN trips_points tpo ON tpo.id = origenes.origen
                INNER JOIN trips_points tpd ON tpd.id = destinos.destino
                GROUP BY tpo.lat, tpo.lng, tpd.lat, tpd.lng
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $frecuencia_origenes_destinos = DB::select($queryOrigenesDestinosFrecuencia, [self::LIMIT_TOP]);

            // Get active users per month data
            $activeUsersPerMonth = ActiveUsersPerMonth::orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'key' => sprintf('%04d-%02d', $item->year, $item->month),
                        'año' => $item->year,
                        'mes' => $item->month,
                        'cantidad' => $item->value,
                        'saved_at' => $item->saved_at
                    ];
                });

            return response()->json([
                'usuarios' => $usuarios,
                'viajes' => $viajes,
                'solicitudes' => $solicitudes,
                'frecuencia_origenes_posterior_ago_2017' => $frecuencia_origenes,
                'frecuencia_destinos_posterior_ago_2017' => $frecuencia_destinos,
                'frecuencia_origenes_destinos_posterior_ago_2017' => $frecuencia_origenes_destinos,
                'usuarios_activos' => $activeUsersPerMonth
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving data'], 500);
        }
    }

    public function moreData() 
    {
        try {
            $queryCalificaciones = "
                SELECT r.user_id_to, u.name, COUNT(*) as rating
                FROM rating r
                INNER JOIN users u ON r.user_id_to = u.id
                WHERE voted = 1
                GROUP BY r.user_id_to, u.name
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $calificaciones = DB::select($queryCalificaciones, [self::LIMIT_RANKING]);

            $queryViajesConductores = "
                SELECT t.user_id, u.name, COUNT(*) as drives
                FROM trips t
                INNER JOIN users u ON t.user_id = u.id
                WHERE t.is_passenger = 0
                GROUP BY t.user_id, u.name
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $conductores = DB::select($queryViajesConductores, [self::LIMIT_RANKING]);

            $queryViajesPasajeros = "
                SELECT tp.user_id, u.name, COUNT(*) as drives
                FROM trip_passengers tp
                INNER JOIN users u ON tp.user_id = u.id
                WHERE tp.request_state = 1
                GROUP BY tp.user_id, u.name
                ORDER BY COUNT(*) DESC
                LIMIT ?
            ";
            
            $pasajeros = DB::select($queryViajesPasajeros, [self::LIMIT_RANKING]);

            return response()->json([
                'ranking_calificaciones' => $calificaciones,
                'ranking_conductores' => $conductores,
                'ranking_pasajeros' => $pasajeros
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Error retrieving rankings data'], 500);
        }
    }
}
