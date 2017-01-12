<?php

namespace STS\Repository; 

use STS\Entities\Trip;
use STS\Entities\TripPoint;
use STS\User;
use Validator;

class TripRepository
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
        $trip = $trip->update($data);
        if ($points) {
            $this->deletePoints($trip);
            $this->addPoints($trip, $points);
        }
        return $trip;
    }

    public function show($id)
    { 
        return Trip::with('points')->whereId($id)->first();
    }

    public function index()
    { 
        return Trip::all();
    }   

    public function delete($trip)
    { 
        return $trip-delete();
    }         
 
    public function addPoints($trip, $points) {
        foreach($points as $point) {
            $p = new TripPoint;
            $p->address = $point["address"];
            $p->json_address = $point["json_address"];
            $p->lat = $point["lat"];
            $p->lng = $point["lng"];
            $trip->points()->save($p);
        }
    }

    public function deletePoints($trip, $points) {
        $trip->points()->delete();
    }

}