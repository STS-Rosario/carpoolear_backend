<?php

namespace STS\Repository;

use Carbon\Carbon;
use STS\Entities\Trip;
use STS\User as UserModel;
use STS\Entities\Subscription as SubscriptionModel;
use STS\Contracts\Repository\Subscription as SubscriptionRepository;

class SubscriptionsRepository implements SubscriptionRepository
{
    public function create(SubscriptionModel $model)
    {
        return $model->save();
    }

    public function update(SubscriptionModel $model)
    {
        return $model->save();
    }

    public function show($id)
    {
        return SubscriptionModel::find($id);
    }

    public function delete(SubscriptionModel $model)
    {
        return $model->delete();
    }

    public function list(UserModel $user, $active = null)
    {
        if ($active == null) {
            return $user->subscriptions;
        } else {
            return $user->subscriptions()->where('state', $active)->get();
        }
    }

    public function search ($user, $trip)
    {
        if (isset($data['strict'])) {
            // Por las dudas
            // $trips = Trip::where(DB::Raw('DATE(trip_date)'), $data['date']);
            // $trips->orderBy('trip_date');
        } else {
            $date_search = $trip->trip_date;
            $from = $date_search->copy()->startOfDay();
            $to = $date_search->copy()->endOfDay();

            $now = Carbon::now('America/Argentina/Buenos_Aires');
            if ($from->lte($now)) {
                $from = $now;
            }
            $query = SubscriptionModel::with('user')->where(function ($q) use ($from, $to) {
                $q->whereNull('trip_date');
                $q->orWhere(function ($q) use ($from, $to) {
                    $q->where('trip_date', '>=', date_to_string($from, 'Y-m-d H:i:s'));
                    $q->where('trip_date', '<=', date_to_string($to, 'Y-m-d H:i:s'));
                });
            });
        }

        $query->where('state', true);

        switch ($trip->friendship_type_id) {
            case Trip::PRIVACY_PUBLIC:
                // Nothings;
                break;
            case Trip::PRIVACY_FRIENDS:
                $users = $user->friends()->pluck('id');
                $query->whereIn('user_id', $users);

                break;
            case Trip::PRIVACY_FOF:
                $users2 = $user->relativeFriends()->pluck('id');
                $users = $user->friends()->pluck('id');
                $query->where(function ($q) use ($users, $users2) {
                    $q->whereIn('user_id', $users);
                    $q->orWhereIn('user_id', $users2);
                });

                break;
        }
        // $points = $trip->points;
        // $this->makeDistance($query, $points[0], 'from');
        // $this->makeDistance($query, $points[count($points) - 1], 'to');

        // TODO take in account route nodes
        $countOrder = 0;
        $nodes = [];
        $processed = 1;
        foreach ($trip->routes as $r) {
            if ($r->processed == 0) {
                $processed = 0;
            }
        }
        if ($processed) {
            foreach ($trip->routes->nodes as $node) {
                $nodes[$countOrder] = $node->id;
                $countOrder++;
            }
            $query->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('to_id');
                    $q->whereIn('from_id', $nodes);
                });
                $q->orWhere(function ($q) {
                    $q->whereNull('from_id');
                    $q->whereIn('to_id', $nodes);
                });
                $q->orWhere(function ($q) {
                    $q->whereNull('from_id');
                    $q->whereNull('to_id');
                });
                // FIXME join with routes_node para una route y verificar que origin es menor que destiny
                /* $q->orWhere(function ($q) {
    
                }); */
            });
            $query->where('is_passenger', $trip->is_passenger);
            return $query->get();
        } 
        return [];
        
    }

    private function makeDistance($query, $point, $name)
    {
        $query->where(function ($q) use ($point, $name) {
            $q->whereNull($name.'_address');
            $q->orWhere(function ($q) use ($point, $name) {
                $sin_lat = sprintf('%.6f', $point->sin_lat);
                $sin_lng = sprintf('%.6f', $point->sin_lng);
                $cos_lat = sprintf('%.6f', $point->cos_lat);
                $cos_lng = sprintf('%.6f', $point->cos_lng);
                $q->whereRaw($name.'_sin_lat * '.$sin_lat.' + '.$name.'_cos_lat * '.$cos_lat.' *  ('.$name.'_cos_lng * '.$cos_lng.' + '.$name.'_sin_lng * '.$sin_lng.') > cos( '.$name.'_radio / 1000.0 / 6371.0)');
            });
        });
    }

    // private function whereLocation($trips, $lat, $lng, $way, $distance = 1000.0)
    // {
    //     $deg2radMultiplier = M_PI / 180.0;
    //     $latd = $lat * $deg2radMultiplier;
    //     $lngd = $lng * $deg2radMultiplier;
    //     $sin_lat = str_replace(',', '.', sin($latd));
    //     $cos_lat = str_replace(',', '.', cos($latd));
    //     $sin_lng = str_replace(',', '.', sin($lngd));
    //     $cos_lng = str_replace(',', '.', cos($lngd));

    //     $distance = max($distance, 1000.0);
    //     $dist = cos(($distance / 1000.0) / 6371.0);
    //     $dist = str_replace(',', '.', $dist);

    //     $trips->whereHas('points', function ($q) use ($way, $sin_lat, $sin_lng, $cos_lat, $cos_lng, $dist) {
    //         if ($way == 'origin') {
    //             $q->where('id', DB::Raw('(select min(`id`) from trips_points where trip_id = `trips`.`id`)'));
    //         }
    //         if ($way == 'destination') {
    //             $q->where('id', DB::Raw('(select max(`id`) from trips_points where trip_id = `trips`.`id`)'));
    //         }
    //         $q->whereRaw('sin_lat * '.$sin_lat.' + cos_lat * '.$cos_lat.' *  (cos_lng * '.$cos_lng.' + sin_lng * '.$sin_lng.') > '.$dist);
    //     });
    // }
}
