<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command; 
use STS\Events\Trip\Alert\HourLeft as  HourLeftEvent;
use STS\Repository\TripRepository;
use STS\Services\Logic\TripsManager;

class TripRemainder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:remainder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create rates from ending trips';

    protected $tripLogic;

    protected $tripRepo;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(TripsManager $logic, TripRepository $repo)
    {
        parent::__construct();
        $this->tripLogic = $logic;
        $this->tripRepo = $repo;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND TripRemainder");
        $time = Carbon::now()->minute(0)->second(0)->addHour();
        $time2 = $time->copy()->minute(59)->second(59);

        $criterias = [
             ['key' => 'trip_date', 'op' => '>=', 'value' => $time->toDateTimeString()],
             ['key' => 'trip_date', 'op' => '<=', 'value' => $time2->toDateTimeString()],
        ];

        $trips = $this->tripRepo->index($criterias, ['user', 'passengerAccepted']);
        foreach ($trips as $trip) {
            if ($trip->passengerAccepted->count() > 0) {
                event(new HourLeftEvent($trip, $trip->user));
                foreach ($trip->passengerAccepted as $p) {
                    event(new HourLeftEvent($trip, $p->user));
                }
            }
        }
    }
}
