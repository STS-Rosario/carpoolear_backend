<?php

namespace STS\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CreateRates::class,
        Commands\TripRemainder::class,
        Commands\RequestRemainder::class,
        Commands\RequestNotAnswer::class,
        Commands\DownloadPoints::class,
        Commands\FacebookImage::class,
        Commands\UpdateUser::class,
        Commands\ConversationCreate::class,
        Commands\BuildNodes::class,
        Commands\BuildRoutes::class,
        Commands\BuildNodesWeights::class,
        Commands\updateTrips::class,
        Commands\RatesAvailability::class,
        Commands\GenerateTripVisibility::class,
        Commands\CleanTripVisibility::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('rate:create')->hourly();

        $schedule->command('trip:remainder')->hourly();

        $schedule->command('trip:request')->dailyAt('12:00')->timezone('America/Argentina/Buenos_Aires');

        $schedule->command('trip:request')->dailyAt('19:00')->timezone('America/Argentina/Buenos_Aires');

        $schedule->command('georoute:build')->everyMinute();

        $schedule->command('node:buildweights')->hourly();

        $schedule->command('rating:availables')->hourly();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
