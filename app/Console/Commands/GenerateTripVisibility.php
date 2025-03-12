<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use STS\Models\Trip; 
use Illuminate\Console\Command;
use STS\Repository\TripRepository;

class GenerateTripVisibility extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:visibility';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate trip visibility';

    protected $tripLogic;
    protected $tripRepo;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(TripRepository $repo)
    {
        $this->tripRepo = $repo;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND GenerateTripVisibility");
        $now = Carbon::now();

        $trips = Trip::where('trip_date', '>=', $now->toDateTimeString())->where('friendship_type_id', '<', Trip::PRIVACY_PUBLIC);
        
        $trips = $trips->get();

        foreach ($trips as $trip) {
            $this->tripRepo->generateTripFriendVisibility($trip);
        }
    }
}
