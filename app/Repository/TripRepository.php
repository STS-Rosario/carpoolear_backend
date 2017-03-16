<?php

namespace STS\Repository;

use STS\Contracts\Repository\Trip as TripRepo;
use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Carbon\Carbon;
use Validator;
use DB;

class TripRepository implements TripRepo
{
    public function create(array $data)
    {
        $points = $data['points'];
        unset($data['points']);
        $trip = Trip::create($data);
        $this->addPoints($trip, $points);
        return $trip;
    }

    public function update($trip, array $data)
    {
        $points = null;
        if (isset($points)) {
            $points = $data['points'];
            unset($data['points']);
        }
        $trip->update($data);
        if ($points) {
            $this->deletePoints($trip);
            $this->addPoints($trip, $points);
        }
        return $trip;
    }

    public function show($id)
    {
        return Trip::with(['user','points'])->whereId($id)->first();
    }

    public function index($user, $data)
    {
        if (isset($data['date'])) { 

            $from = parse_date($data['date'])->subDays(5);
            $to   = parse_date($data['date'])->addDays(5);
            //$trips = Trip::where(DB::Raw('DATE(trip_date)') , $data['date'] );
            $trips = Trip::whereBetween(DB::Raw('DATE(trip_date)') , [date_to_string($from), date_to_string($to)] );
            $trips->orderBy(DB::Raw("DATEDIFF(DATE(trip_date), '" . $data['date'] . "' )"));
            //$trips->setBindings([$data['date']]);
        } else {
            $trips = Trip::where('trip_date', '>=', Carbon::Now());
            $trips->orderBy('trip_date');
        }
        
        $trips->where(function ($q) use ($user) {
            if ($user) {
                $q->whereUserId($user->id);
                $q->orWhere(function ($q) use ($user) {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
                    $q->orWhere(function ($q) use ($user) {
                        $q->whereFriendshipTypeId(Trip::PRIVACY_FRIENDS);
                        $q->whereHas('user.friends', function ($q) use ($user) {
                            $q->whereId($user->id);
                        });
                    });
                    $q->orWhere(function ($q) use ($user) {
                        $q->whereFriendshipTypeId(Trip::PRIVACY_FOF);
                        $q->where(function ($q) use ($user) {
                            $q->whereHas('user.friends', function ($q) use ($user) {
                                $q->whereId($user->id);
                            });
                            $q->orWhereHas('user.friends.friends', function ($q) use ($user) {
                                $q->whereId($user->id);
                            });
                        });
                    });
                });
            } else {
                $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
            }
        });

        if (isset($data['origin_lat']) && isset($data['origin_lng'])) {
            $distance = 1000.0;
            if (isset($data["origin_radio"])) {
                $distance = floatval($data["origin_radio"]);
            }
            $this->whereLocation($trips, $data["origin_lat"], $data["origin_lng"], "origin", $distance);
        }

        if (isset($data['destination_lat']) && isset($data['destination_lng'])) {
            $distance = 1000.0;
            if (isset($data["destination_radio"])) {
                $distance = floatval($data["destination_radio"]);
            }
            $this->whereLocation($trips, $data["destination_lat"], $data["destination_lng"], "destination", $distance);
        }

        $trips->with(['user', 'points']);
        
        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize   = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($trips, $pageNumber, $pageSize);

        //return $trips->get();
        // [FALTA] Tema de la localizacion para viajes publicos
    }

    private function whereLocation($trips, $lat, $lng, $way, $distance = 1000.0)
    { 
        $deg2radMultiplier = M_PI / 180.0;
        $latd = $lat * $deg2radMultiplier;
        $lngd = $lng * $deg2radMultiplier;
        $sin_lat = str_replace(',', '.', sin($latd));
        $cos_lat = str_replace(',', '.', cos($latd));
        $sin_lng = str_replace(',', '.', sin($lngd));
        $cos_lng = str_replace(',', '.', cos($lngd));

        $distance = max($distance, 1000.0);
        $dist = cos(($distance /1000.0) / 6371.0);
        $dist = str_replace(',', '.', $dist);

        $trips->whereHas('points', function ($q) use ($way, $sin_lat, $sin_lng, $cos_lat, $cos_lng, $dist) {
            if ($way == "origin") {
                $q->where("id", DB::Raw("(select min(`id`) from trips_points where trip_id = `trips`.`id`)"));
            }
            if ($way == "destination") {
                $q->where("id", DB::Raw("(select max(`id`) from trips_points where trip_id = `trips`.`id`)"));
            }
            $q->whereRaw("sin_lat * " . $sin_lat . " + cos_lat * " . $cos_lat . " *  (cos_lng * " . $cos_lng . " + sin_lng * "  . $sin_lng . ") > " . $dist);
        });
    }

    public function delete($trip)
    {
        return $trip->delete();
    }
 
    public function addPoints($trip, $points)
    {
        foreach ($points as $point) {
            $p = new TripPoint;
            $p->address = $point["address"];
            $p->json_address = $point["json_address"];
            $p->lat = $point["lat"];
            $p->lng = $point["lng"];
            $trip->points()->save($p);
        }
    }

    public function deletePoints($trip, $points)
    {
        $trip->points()->delete();
    }
}
