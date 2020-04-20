<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Services\Logic\RoutesManager as RoutesManager;
use STS\Contracts\Repository\Routes as RoutesRepo;
use STS\Events\Trip\Create  as CreateEvent;
use STS\Entities\Route;
use STS\Entities\Trip;

class BuildRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'georoute:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create geo node route for trips';

    protected $routeLogic;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(RoutesManager $routeLogic)
    {
        $this->routeLogic = $routeLogic;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info('Route builder ');
        $route = Route::where('processed', 0)->with(['origin', 'destiny'])->first();
        if ($route) {
            try {
                $this->routeLogic->createRoute($route);
                $tripsQuery = Trip::where('trip_date', '>=', Carbon::Now());
                $tripsQuery->whereHas('routes', function ($q) use ($route) {
                    $q->where('routes.id', $route->id);
                });
                $trips = $tripsQuery->get();
                foreach ($trips as $trip) {
                    event(new CreateEvent($trip));
                }
            } catch (\Exception $ex) {
                \Log::info('Route builder ex');
                \Log::info($ex);
            }
        }
    }
}
