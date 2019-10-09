<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use STS\Entities\Route;
use STS\Entities\NodeGeo;
use STS\Entities\TripPoint;
use STS\Contracts\Repository\Trip as TripRepo;

class TripRepository implements TripRepo
{
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
            if ($origin->id > 0 && $destiny->id > 0) {
                $route = Route::where('from_id', $origin->id)->where('to_id', $destiny->id)->first();
                if (!$route) {
                    $route = new Route();
                    $route->from_id = $origin->id;
                    $route->to_id = $destiny->id;
                    $route->processed = false;
                    $route->save();
                }
                $routeIds[] = $route->id;
            }
        }
        if (count($routeIds)) {
            $trip->routes()->sync($routeIds);
        }

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
            $trip = Trip::with(['user', 'points', 'car', 'passenger', 'ratings'])->whereId($id)->first();
            \Log::info($trip);
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
            $trips->whereHas('passengerAccepted', function ($q) use ($user, $userId) {
                $q->where('request_state', Passenger::STATE_ACCEPTED);
                $q->where('user_id', $userId);
            });
        }
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
            $trips->whereHas('passengerAccepted', function ($q) use ($user, $userId) {
                $q->where('request_state', Passenger::STATE_ACCEPTED);
                $q->where('user_id', $userId);
            });
        }
        $trips->orderBy('trip_date');
        $trips->with(['user', 'points', 'passengerAccepted', 'passengerAccepted.user', 'car']);

        return $trips->get();
    }

    public function search($user, $data)
    {       
        $trips = Trip::query()->with(['routes', 'routes.nodes']);
        if (isset($data['is_passenger'])) {
            $trips->where('is_passenger', parse_boolean($data['is_passenger']));
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
                    $trips = Trip::where('trip_date', '>=', date_to_string($from, 'Y-m-d H:i:s'));
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
        if (!$user->is_admin) {
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
        }
        if (isset($data['origin_id'])) {
            $trips->whereHas('routes.nodes', function ($q) use ($data) {
                $q->where('nodes_geo.id', $data['origin_id']);
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
            $trips->whereHas('routes.nodes', function ($q) use ($data) {
                $q->where('nodes_geo.id', $data['destination_id']);
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

        $trips->with(['user', 'user.accounts', 'points', 'passenger','passengerAccepted', 'car', 'ratings']);
        
        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($trips, $pageNumber, $pageSize);
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
}
