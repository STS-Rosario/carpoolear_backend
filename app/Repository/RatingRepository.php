<?php

namespace STS\Repository;

use DB;
use STS\User;
use Carbon\Carbon;
use STS\Entities\Rating as RatingModel;
use STS\Entities\Passenger as PassengerModel;
use STS\Contracts\Repository\IRatingRepository;

class RatingRepository implements IRatingRepository
{

    public function getRating($id)
    {
        return RatingModel::find($id);
    }

    public function getRatings($user, $data) 
    {
        $ratings = RatingModel::where('user_id_to', $user->id);

        $pageNumber = isset($data['page']) ? $data['page'] : null;
        $pageSize = isset($data['page_size']) ? $data['page_size'] : null;

        return make_pagination($ratings, $pageNumber, $pageSize);
    }
    
    public function getPendingRatings($user) 
    {
        $trips_as_driver = PassengerModel::where('user_id', $user->id)
                                ->where('passenger_type', PassengerModel::TYPE_CONDUCTOR)
                                ->whereHas('trip', function ($query) {
                                    $query->where('trip_date', '>=', DB::Raw(' DATE_SUB(NOW(), INTERVAL ' . RatingModel::RATING_INTERVAL .' DAY)') );
                                    $query->where('trip_date', '<', DB::Raw(' NOW()'));
                                })->get();

        $trips_as_passengers = PassengerModel::where('user_id', $user->id)
                                    ->where('passenger_type', PassengerModel::TYPE_PASAJERO)
                                    ->where(function ($query) {
                                        $query->where('request_state', PassengerModel::STATE_ACCEPTED);
                                        $query->orWhere('request_state', PassengerModel::STATE_CANCELED);
                                    })
                                    ->whereHas('trip', function ($query) {
                                        $query->where('trip_date', '>=', DB::Raw(' DATE_SUB(NOW(), INTERVAL ' . RatingModel::RATING_INTERVAL .' DAY)') );
                                        $query->where('trip_date', '<', DB::Raw(' NOW()'));
                                    })->get();

        $map_function = function ($trip) { return $trip->id; };

		$trips_as_driver = array_map($map_function, $trips_as_driver);
		$trips_as_passengers = array_map($map_function, $trips_as_passengers);

		$pending_as_driver = array();
		$pending_as_passenger = array();	

        if(count($trips_as_driver) > 0) {
            $pending_as_driver = PassengerModel::where_in('trip_id', $trips_as_driver)
                                    ->where('passenger_type', PassengerModel::TYPE_PASAJERO)
                                    ->whereDoesntHave('ratingReceived', function ($query) use ($user) {
                                        $query->where('user_id_from', $user->id);
                                    })->get();

            //Todos los pasajeros para un viaje dado, que no tengan una calificacion mia

        }
        if(count($pending_as_passenger) > 0) {
            $pending_as_passenger = PassengerModel::where_in('trip_id', $trips_as_passengers)
                                        ->where('passenger_type', PassengerModel::TYPE_CONDUCTOR)
                                        ->whereDoesntHave('ratingGiven', function ($query) use ($user) {
                                            $query->where('user_id_from', $user->id);
                                        })->get();
        }
        return  array_merge($pending_as_driver, $pending_as_passenger);	
        /*
			
		if (count($trip_as_conductor)>0) {	
			$pendientes1 = DB::table('trip_passengers as tp')
								->where_in("tp.trip_id",$trip_as_conductor)
								->where("tp.request_state","=" , trip_request_state::Aceptado)
								->where("tp.passenger_type","=",passenger_types::Pasajero)
								->left_join("calificaciones as c" , function($join) use ($userID)
					        	{
									$join->on('c.trip_id', '=', 'tp.trip_id');
									$join->on('c.to_id', '=', 'tp.user_id');
                                    $join->on('c.from_id','=',DB::Raw($userID));			
								})
								->where_null("c.to_id")
								->get(array("tp.trip_id","tp.user_id","tp.passenger_type")); 	
			//print_r(DB::last_query());die;					
		}	
		if (count($trip_as_pasajero)>0) {		
			$pendientes2 = DB::table('trip_passengers as tp')
								->where_in("tp.trip_id",$trip_as_pasajero)
								->where("tp.request_state","=" , trip_request_state::Aceptado)
								->where("tp.passenger_type","=",passenger_types::Conductor)
								->left_join("calificaciones as c" , function($join) use ($userID)
					        	{
									$join->on('c.trip_id', '=', 'tp.trip_id');
									$join->on('c.to_id', '=', 'tp.user_id');
                                    $join->on('c.from_id','=',DB::Raw($userID));			
								})
								->where_null("c.to_id")
								->get(array("tp.trip_id","tp.user_id","tp.passenger_type"));
		}
		return  array_merge($pendientes1,$pendientes2);	
        */

        //TODO: Implement this


        return array();
    }
    
    public function rateUser ($user_from, $user_to, $trip, $value, $comment) 
    {
        $newRating= [
            'trip_id' => $trip->id,
            'user_id_from' => $user_from->id,
            'user_id_to' => $user_to->id,
            'value' => $value ? RatingModel::STATE_POSITIVO : RatingModel::STATE_NEGATIVO,
            'comment' => $comment
        ];

        $newRating = RatingModel::create($newRating);

        return $newRating;
    }

    public function replyRating ($rating, $comment)
    {
        $rating = $this->getRating($rating->id);

        $rating->reply_comment = $comment;
        $rating->reply_comment_created_at = date("Y-m-d H:i:s");

        $rating->save();

        return $rating;
    }



}
