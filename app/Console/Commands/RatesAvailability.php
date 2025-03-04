<?php

namespace STS\Console\Commands;

use STS\Models\Rating;
use Illuminate\Console\Command;
use Carbon\Carbon;
use DB;


class RatesAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rating:availables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Makes rates available';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Rating::where('created_at', '<', Carbon::Now()->subDays(Rating::RATING_INTERVAL))
        ->where('voted', '=', DB::raw(1))
        ->update(['available' => 1]);

        
        $rates = DB::table('rating as r')->where('r.created_at', '>=', Carbon::Now()->subDays(Rating::RATING_INTERVAL))->where('r.voted', 1);
        $rates->join("rating as r2", function($join) {
            $join->on('r.trip_id', '=', 'r2.trip_id');
            $join->on('r.user_id_from', '=', 'r2.user_id_to');
            $join->on('r.user_id_to', '=', 'r2.user_id_from');
            $join->on('r2.voted', '=', DB::raw(1));
            $join->on('r.voted', '=', DB::raw(1));
        });

        $rates->update(['r.available' => 1]);
    }
}
