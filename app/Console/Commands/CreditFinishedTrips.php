<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Models\Passenger;
use STS\Models\Trip;
use STS\Services\Logic\UsersManager;

class CreditFinishedTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trips:credit-finished {--limit=100 : Max trips to credit per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Credit trips_count for drivers and accepted passengers when trip_date passes';

    protected $usersManager;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(UsersManager $usersManager)
    {
        parent::__construct();
        $this->usersManager = $usersManager;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info('COMMAND CreditFinishedTrips');

        $now = Carbon::now();

        // Find trips that have:
        // 1. trip_date in the past (finished)
        // 2. NOT soft-deleted
        // 3. NOT yet credited (trips_count_credited_at is null)
        $limit = max(1, (int) $this->option('limit'));

        $finishedTrips = Trip::whereNotNull('trip_date')
            ->where('trip_date', '<', $now)
            ->whereNull('trips_count_credited_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $creditedCount = 0;

        foreach ($finishedTrips as $trip) {
            // Refresh trips_count for the driver
            $driver = $trip->user;
            if ($driver) {
                $this->usersManager->refreshTripsCount($driver->fresh());
                $creditedCount++;
            }

            // Refresh trips_count for each accepted passenger
            $acceptedPassengers = $trip->passenger()
                ->where('request_state', Passenger::STATE_ACCEPTED)
                ->get();

            foreach ($acceptedPassengers as $passenger) {
                $passengerUser = $passenger->user;
                if ($passengerUser) {
                    $this->usersManager->refreshTripsCount($passengerUser->fresh());
                    $creditedCount++;
                }
            }

            // Mark this trip as credited
            $trip->trips_count_credited_at = $now;
            $trip->save();
        }

        \Log::info("CreditFinishedTrips: Credited {$creditedCount} users from {$finishedTrips->count()} trips");

        return 0;
    }
}
