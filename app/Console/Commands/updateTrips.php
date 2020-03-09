<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Services\Logic\RoutesManager as RoutesManager;
use STS\Contracts\Repository\Routes as RoutesRepository;
use STS\Entities\Route;
use STS\Entities\Trip;
use STS\Entities\NodeGeo;

class updateTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node:updateTrips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create and assign routes to old trips';

    protected $routeLogic;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct(RoutesManager $routeLogic, RoutesRepository $routeRepo)
    {
        $this->routeLogic = $routeLogic;
        $this->routeRepo = $routeRepo;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // iterar x viajes creados, asignar ruta / crearla
        $query = Trip::query()->with(['routes', 'routes.nodes', 'points']);
        $query->where('trip_date', '>=', '2017-09-01 00:00:00'); 
        $query->whereNull('route_id');
        $query->take(5000);
        $trips = $query->get();
        \Log::info("Trips: " . count($trips));
        foreach($trips as $trip) {
            $this->info("Trip Id: " . $trip->id);
            \Log::info("Trip Id: " . $trip->id);
            
            if (count($trip->points) == 0) {
                $this->info("No point" . $trip->id);
                \Log::info("No point" . $trip->id);
                continue;
            }

            $from = $trip->points[0];
            $to = $trip->points[1];
            
            
            $fromStart = new NodeGeo;
            $fromStart->lat = $from->lat - 0.05;
            $fromStart->lng = $from->lng - 0.1;
            $fromEnd = new NodeGeo;
            $fromEnd->lat = $from->lat + 0.05;
            $fromEnd->lng = $from->lng + 0.1;
            $fromNode = $this->getPotentialNode($fromStart, $fromEnd);
            
            $toStart = new NodeGeo;
            $toStart->lat = $to->lat - 0.05;
            $toStart->lng = $to->lng - 0.1;
            $toEnd = new NodeGeo;
            $toEnd->lat = $to->lat + 0.05;
            $toEnd->lng = $to->lng + 0.1;
            $toNode = $this->getPotentialNode($toStart, $toEnd);
            
            
            if ($fromNode && $toNode) {
                $route = Route::where('from_id', $fromNode->id)->where('to_id', $toNode->id)->first();
                if ($route) {
                    $trip->routes()->sync([$route->id]);
                } else {
                    $route = new Route();
                    $route->from_id = $fromNode->id;
                    $route->to_id = $toNode->id;
                    $route->processed = false;
                    $route->save();

                    $trip->routes()->sync([$route->id]);
                }
            } else {
                $this->info("ERROR NO SE ENCONTRO NODO " . $trip->id);
                \Log::info("ERROR NO SE ENCONTRO NODO " . $trip->id);
                if (!$fromNode) {
                    $this->info("name " . $from->address);
                    \Log::info("name " . $from->address);
                }
                if (!$toNode) {
                    $this->info("name " . $to->address);
                    \Log::info("name " . $to->address);
                }
            }      
            
            $trip->route_id = 1;
            $trip->save();      
        }

    }

    public function getPotentialNode ($n1, $n2) {
        $maxLat = 0;
        $minLat = 0;
        $minLng = 0;
        $maxLng = 0;
        if ($n1->lat > $n2->lat) {
            $maxLat = $n1->lat;
            $minLat = $n2->lat;
        } else {
            $maxLat = $n2->lat;
            $minLat = $n1->lat;
        }
        if ($n1->lng > $n2->lng) {
            $maxLng = $n1->lng;
            $minLng = $n2->lng;
        } else {
            $maxLng = $n2->lng;
            $minLng = $n1->lng;
        }
        $query = NodeGeo::whereBetween('lat', [$minLat, $maxLat]);
        $query->whereBetween('lng', [$minLng, $maxLng]);
        return $query->first();
    }
}
