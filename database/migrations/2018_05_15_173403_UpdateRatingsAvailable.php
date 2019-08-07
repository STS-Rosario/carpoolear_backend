<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateRatingsAvailable extends Migration
{
    public function up()
    {
        DB::unprepared('CREATE PROCEDURE update_rating_availability(
            IN _id INT,
            IN _trip_id INT,
            IN _user_id_from INT,
            IN _user_id_to INT
          )
          BEGIN
            UPDATE rating ra
                  SET ra.available = CASE
                      WHEN (ra.created_at <=  ( Now() - INTERVAL 15 day ))  THEN 1
                      WHEN (
                          (SELECT count(*)
                          FROM (SELECT * FROM rating) AS r
                                 INNER JOIN (SELECT * FROM rating) AS r2
                                    ON r.trip_id = r2.trip_id
                                         AND r.user_id_from = r2.user_id_to
                                         AND r.user_id_to = r2.user_id_from
                                         AND r.voted = r2.voted 
                               WHERE r.id = _id) > 0
                      ) THEN 1
                      ELSE 0
                  END 
                  WHERE ra.id != _id
                        AND ra.trip_id = _trip_id
                        AND ra.user_id_to = _user_id_from
                        AND ra.user_id_from = _user_id_to
                        AND ra.voted = 1;
          END;;');
    }

    public function down()
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS update_rating_availability');
    }
}
