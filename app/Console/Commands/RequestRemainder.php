<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use STS\Models\Trip;
use Illuminate\Console\Command;
use STS\Events\Trip\Alert\RequestRemainder as  RequestRemainderEvent;

class RequestRemainder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:request';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify pending requests';

    protected $tripLogic;

    protected $tripRepo;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
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
        \Log::info("COMMAND RequestRemainder");
        $now = Carbon::now();

        $trips = Trip::where('trip_date', '>=', $now->toDateTimeString())->where('is_passenger', 0);
        $trips->has('passengerPending');
        $trips = $trips->get();

        foreach ($trips as $trip) {
            $days = (int) $now->diffInDays($trip->trip_date);
            $weeks = $days / 7;
            if ($weeks < 1) { // Last Week
                event(new RequestRemainderEvent($trip));
            } elseif ($weeks < 2) { // first Week of notifications
                if ($days % 2 == 0) {
                    event(new RequestRemainderEvent($trip));
                }
            }
        }
    }
}
