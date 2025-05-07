<?php

namespace STS\Repository;

use DB;
use STS\Models\User;
use Carbon\Carbon;
use STS\Models\Trip;
use STS\Models\Passenger;
use STS\Models\Route;
use STS\Models\NodeGeo;
use STS\Models\TripPoint;
use STS\Events\Trip\Create  as CreateEvent;

class TripRepository
{
    private function getPotentialNode ($point) {
        $n1 = new NodeGeo;
        $n1->lat = $point['lat'] - 0.05;
        $n1->lng = $point['lng'] - 0.1;
        $n2 = new NodeGeo;
        $n2->lat = $point['lat'] + 0.05;
        $n2->lng = $point['lng'] + 0.1;
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
        $query = NodeGeo::whereBetween('lat', [$minLat, $maxLat]);
        $query->whereBetween('lng', [$minLng, $maxLng]);
        return $query->first();
    }
    public function generateTripFriendVisibility ($trip) {
        if ($trip->friendship_type_id < 2) {
            if ($trip->friendship_type_id == 1) {
                // friend of friends
                $query = 'INSERT INTO user_visibility_trip
                            (SELECT f.uid2, t.id
                                FROM trips t
                                INNER JOIN friends f ON t.user_id = f.uid1 AND f.state = 1
                                WHERE t.friendship_type_id = 1
                                    AND t.id = ?
                            )
                            UNION
                            (SELECT f2.uid2, t.id
                                FROM trips t
                                INNER JOIN friends f ON t.user_id = f.uid1 AND f.state = 1
                                INNER JOIN friends f2 ON f.uid2 = f2.uid1 AND f2.state = 1
                                WHERE t.friendship_type_id = 1
                                    AND t.id = ?
                            )
                ';
                DB::insert($query, [$trip->id, $trip->id]);
            } else {
                // only friends
                $query = 'INSERT INTO user_visibility_trip
                                SELECT f.uid2, t.id
                                    FROM trips t
                                    INNER JOIN friends f ON t.user_id = f.uid1 AND f.state = 1
                                    WHERE t.friendship_type_id = 0
                                        AND t.id = ?
                ';
                DB::insert($query, [$trip->id]);
            }
        }
    }

    public function create(array $data)
    {
        $points = $data['points'];
        unset($data['points']);
        $trip = Trip::create($data);
        $this->addPoints($trip, $points);
        // obtener ruta o crear
        $routeIds = [];
        for ($i = 1; $i < count($points); $i++) {
            $origin = is_array($points[$i - 1]['json_address']) ? (object)$points[$i - 1]['json_address'] : json_decode($points[$i - 1]['json_address']);
            $destiny = is_array($points[$i]['json_address']) ? (object)$points[$i]['json_address'] : json_decode($points[$i]['json_address']);
            if (!isset($origin->id) || !isset($destiny->id)) {
                $origin = $this->getPotentialNode($points[$i - 1]);
                $destiny = $this->getPotentialNode($points[$i]);
            }
            if (isset($origin->id) && $origin->id > 0 && isset($destiny->id) && $destiny->id > 0) {
                $route = Route::where('from_id', $origin->id)->where('to_id', $destiny->id)->first();
                if (!$route) {
                    $route = new Route();
                    $route->from_id = $origin->id;
                    $route->to_id = $destiny->id;
                    $route->processed = false;
                    $route->save();
                    
                    $nodes = [$origin->id, $destiny->id];
                    $route->nodes()->sync($nodes);
                    
                } else {
                    if ($route->processed) {
                        event(new CreateEvent($trip));
                    }
                }
                $routeIds[] = $route->id;
            }
        }
        if (count($routeIds)) {
            $trip->routes()->sync($routeIds);
        }

        $this->generateTripFriendVisibility($trip);
        return $trip;
    }

    public function update($trip, array $data)
    {
        $points = null;
        if (isset($data['points'])) {
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

    public function show($user, $id)
    {
        if ($user->is_admin) {
            $trip = Trip::with(['user', 'points', 'car', 'passenger', 'ratings'])->withTrashed()->whereId($id)->first();
            return $trip;
        } else {
            return Trip::with(['user', 'points'])->whereId($id)->first();
        }
    }

    public function index($criterias, $withs = [])
    {
        $trips = Trip::orderBy('trip_date');
        foreach ($criterias as $item) {
            $first = $item['key'];
            if (strpos($first, '(')) {
                $first = DB::Raw($first);
            }
            if (isset($item['op'])) {
                $trips->where($first, $item['op'], $item['value']);
            } else {
                $trips->where($first, $item['value']);
            }
        }
        if ($withs) {
            $trips->with($withs);
        }

        return $trips->get();
    }

    public function getTrips($user, $userId, $asDriver)
    {
        $trips = Trip::where('trip_date', '>=', Carbon::Now());

        if ($asDriver) {
            $trips->where('user_id', $userId);
        } else {
            /* $trips->whereHas('passengerAccepted', function ($q) use ($user) {
                $q->where('request_state', Passenger::STATE_ACCEPTED);
                $q->where('user_id', $user->id);
            }); */
            $trips->join('trip_passengers', 'trips.id', '=', 'trip_passengers.trip_id');
            $trips->whereNull('trips.deleted_at');
            $trips->where('trip_passengers.user_id', $user->id);
            $trips->where('trip_passengers.request_state', Passenger::STATE_ACCEPTED);
        }

        $trips->select('trips.*');
        $trips->orderBy('trip_date');
        $trips->with(['user', 'points', 'passengerAccepted', 'passengerAccepted.user', 'car']);

        return $trips->get();
    }

    public function getOldTrips($user, $userId, $asDriver)
    {
        $trips = Trip::where('trip_date', '<', Carbon::Now());

        if ($asDriver) {
            $trips->where('user_id', $userId);
        } else {
            /* $trips->whereHas('passengerAccepted', function ($q) use ($user) {
                $q->where('request_state', Passenger::STATE_ACCEPTED);
                $q->where('user_id', $user->id);
            }); */
            $trips->join('trip_passengers', 'trips.id', '=', 'trip_passengers.trip_id');
            $trips->whereNull('trips.deleted_at');
            $trips->where('trip_passengers.user_id', $user->id);
            $trips->where('trip_passengers.request_state', Passenger::STATE_ACCEPTED);
        }

        $trips->select('trips.*');
        $trips->orderBy('trip_date');
        $trips->with(['user', 'points', 'passengerAccepted', 'passengerAccepted.user', 'car']);

        return $trips->get();
    }

    public function search($user, $data)
    {

        $trips = Trip::query()->with(['routes']);
        if (isset($data['is_passenger'])) {
            $trips->where('is_passenger', parse_boolean($data['is_passenger']));
        }
        if (isset($data['is_admin']) && strval($data['is_admin']) === 'true') {
            $trips->withTrashed();
        }
        
        if (isset($data['from_date']) || isset($data['to_date'])) {
            if (isset($data['from_date'])) {
                $date_from = parse_date($data['from_date']);
                
                $trips = $trips->where('trip_date', '>=', date_to_string($date_from, 'Y-m-d H:i:s'));
                $trips->orderBy('trip_date');
            }
            if (isset($data['to_date'])) {
                $date_to = parse_date($data['to_date']);
                
                $trips->where('trip_date', '<=', date_to_string($date_to, 'Y-m-d H:i:s'));             
                $trips->orderBy('trip_date');
            }
        } else {
            if (isset($data['date'])) {
                if (isset($data['strict'])) {
                    $trips = $trips->where(DB::Raw('DATE(trip_date)'), $data['date']);
                    $trips->orderBy('trip_date');
                } else {
                    $date_search = parse_date($data['date']);
                    $from = $date_search->copy()->subDays(3);
                    $to = $date_search->copy()->addDays(3);

                    $now = Carbon::now('America/Argentina/Buenos_Aires');
                    if ($from->lte($now)) {
                        $from = $now;
                    }
                    $trips->where('trip_date', '>=', date_to_string($from, 'Y-m-d H:i:s'));
                    $trips->where('trip_date', '<=', date_to_string($to, 'Y-m-d H:i:s'));
                    $trips->orderBy(DB::Raw("IF(ABS(DATEDIFF(DATE(trip_date), '".date_to_string($date_search)."' )) = 0, 0, 1)"));
                    $trips->orderBy('trip_date');
                }
                //$trips->setBindings([$data['date']]);
            } else {
                if (!isset($data['history'])) {
                    $trips = $trips->where('trip_date', '>=', Carbon::Now());
                    $trips->orderBy('trip_date');
                }
            }
        }
        if (isset($data['user_id'])) {
            $trips->whereUserId($data['user_id']);
        }
        
        if ($user && !$user->is_admin) {
            $trips->where(function ($q) use ($user) {
                if ($user) {
                    $q->whereUserId($user->id);
                    $q->orWhere(function ($q) use ($user) {
                        $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
                        $q->orWhere(function ($q) use ($user) {
                            $q->where('friendship_type_id', '<' , Trip::PRIVACY_PUBLIC);
                            $q->whereHas('userVisibility', function ($q) use ($user) {
                                $q->where('user_id', $user->id);
                            });
                        });
                    });
                } else {
                    $q->whereFriendshipTypeId(Trip::PRIVACY_PUBLIC);
                }
            });
        }
        if (isset($data['origin_id']) && isset($data['destination_id'])) {

            $trips->whereHas('routes', function ($q) use ($data) {
                $q->where('routes.from_id', $data['origin_id']);
                $q->where('routes.to_id', $data['destination_id']);
            });
        } else {
            if (isset($data['origin_id'])) {

                $trips->whereHas('routes', function ($q) use ($data) {
                    $q->where('routes.from_id', $data['origin_id']);
                });
            } else {
                if (isset($data['origin_lat']) && isset($data['origin_lng'])) {
                    $distance = 1000.0;
                    if (isset($data['origin_radio'])) {
                        $distance = floatval($data['origin_radio']);
                    }
                    $this->whereLocation($trips, $data['origin_lat'], $data['origin_lng'], 'origin', $distance);
                }
            }
            if (isset($data['destination_id'])) {
                $trips->whereHas('routes', function ($q) use ($data) {
                    $q->where('routes.to_id', $data['destination_id']);
                });
            } else {
                if (isset($data['destination_lat']) && isset($data['destination_lng'])) {
                    $distance = 1000.0;
                    if (isset($data['destination_radio'])) {
                        $distance = floatval($data['destination_radio']);
                    }
                    $this->whereLocation($trips, $data['destination_lat'], $data['destination_lng'], 'destination', $distance);
                }
            }
        }

        $trips->with([
            'user', 
            'user.accounts', 
            'points', 
            'passenger',
            'passengerAccepted', 
            'car', 
            'ratings'
        ]);
        
        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;
        
        // DB::enableQueryLog();
        $pagination = make_pagination($trips, $pageNumber, $pageSize);
        // $pagination = $trips->take(7)->get();
        // \Log::info(DB::getQueryLog());
        return $pagination;
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
        $dist = cos(($distance / 1000.0) / 6371.0);
        $dist = str_replace(',', '.', $dist);

        $trips->whereHas('points', function ($q) use ($way, $sin_lat, $sin_lng, $cos_lat, $cos_lng, $dist) {
            if ($way == 'origin') {
                $q->where('id', DB::Raw('(select min(`id`) from trips_points where trip_id = `trips`.`id`)'));
            }
            if ($way == 'destination') {
                $q->where('id', DB::Raw('(select max(`id`) from trips_points where trip_id = `trips`.`id`)'));
            }
            $q->whereRaw('sin_lat * '.$sin_lat.' + cos_lat * '.$cos_lat.' *  (cos_lng * '.$cos_lng.' + sin_lng * '.$sin_lng.') > '.$dist);
        });
    }

    public function delete($trip)
    {
        return $trip->delete();
    }

    public function addPoints($trip, $points)
    {
        foreach ($points as $point) {
            $p = new TripPoint();
            if (isset($point['address'])) {
                $p->address = $point['address'];
            } else {
                try {
                    if (is_array($point['json_address'])) {
                        $p->address = $point['json_address']['ciudad'];
                    } else {
                        $json = json_decode($point['json_address']);
                        $p->address = $json->ciudad;
                    }
                } catch (Exception $ex) {
                    $p->address = '';
                }
            }
            $p->json_address = $point['json_address'];
            $p->lat = $point['lat'];
            $p->lng = $point['lng'];
            $trip->points()->save($p);
        }
    }

    public function shareTrip($user, $other) {
        $trip = Trip::with(['user']);
        $trip = $trip->where('user_id', '=', $user->id);
        $trip = $trip->where('trip_date', '>=', Carbon::Now());
        $trip = $trip->whereHas('passengerAccepted', function ($query) use ($other) {
            $query->where('user_id', '=', $other->id);
        });

        if ($trip->first()) {
            return true;
        }
        return false;
    }

    public function deletePoints($trip)
    {
        $trip->points()->delete();
    }

    public function simplePrice($distance)
    {
        return $distance * config('carpoolear.fuel_price') / 1000;
    }
    
    public function getTripByTripPassenger ($transaction_id)
    {
        return Passenger::where('id', $transaction_id)->first();
    }

    public function hideTrips ($user) {
        return Trip::where('user_id', $user->id)
                        ->where('trip_date', '>=', Carbon::Now())
                        ->update(['deleted_at' => '2000-01-01']);
    }

    public function unhideTrips ($user) {
        return Trip::onlyTrashed()
                        ->where('user_id', $user->id)
                        ->where('trip_date', '>=', Carbon::Now())
                        ->where('deleted_at', '2000-01-01 00:00:00')
                        ->update(['deleted_at' => null]);
    }

    public function getRecentTrips($userId, $hours)
    {
        return Trip::where('user_id', $userId)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->get();
    }
}
