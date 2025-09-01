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
        Commands\SendAnnouncement::class,
        Commands\TestAnnouncement::class,
        Commands\EvaluateBadges::class,
        Commands\CalculateActiveUsersPerMonth::class,

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

        // Evaluate badges daily at 2 AM
        // $schedule->command('badges:evaluate')->dailyAt('02:00')->timezone('America/Argentina/Buenos_Aires');

        // Calculate active users per month on the 1st of each month at 3 AM
        $schedule->command('users:calculate-active-per-month')->monthlyOn(1, '03:00')->timezone('America/Argentina/Buenos_Aires');

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
