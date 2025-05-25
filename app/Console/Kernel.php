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
        Commands\AnonymizeUser::class,
        Commands\UpdateUser::class,
        Commands\ConversationCreate::class,
        Commands\BuildNodes::class,
        Commands\BuildRoutes::class,
        Commands\BuildNodesSuburb::class,
        Commands\BuildNodesWeights::class,
        Commands\updateTrips::class,
        Commands\RatesAvailability::class,
        Commands\GenerateTripVisibility::class,
        Commands\CleanTripVisibility::class,
        Commands\EmailMessageNotification::class,

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
        
        $schedule->command('rating:availables')->everyMinute();

        $schedule->command('trip:request')->dailyAt('12:00')->timezone('America/Argentina/Buenos_Aires');

        $schedule->command('trip:request')->dailyAt('19:00')->timezone('America/Argentina/Buenos_Aires');

        $schedule->command('trip:visibilityclean')->dailyAt('03:00')->timezone('America/Argentina/Buenos_Aires');

        // $schedule->command('georoute:build')->everyMinute();

        $schedule->command('node:buildweights')->hourly();

        $schedule->command('messages:email')->everyTenMinutes();

        // TODO: add a job to check if trip is awaiting_payment after 30 minutes and send push/email?
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
