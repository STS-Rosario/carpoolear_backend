<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Services\Logic\RoutesManager as RoutesManager;
use STS\Contracts\Repository\Routes as RoutesRepo;
use STS\Entities\Route;
use STS\Entities\NodeGeo;
use DB;

class BuildNodesWeights extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node:buildweights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the weights for nodes';

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
        // Borro todas las importancias

        $query = "
            SELECT id, SUM(importance) as importance FROM (
                (SELECT id, 1000 as importance FROM nodes_geo WHERE type = 'city')
                UNION
                (SELECT id, 500 as importance FROM nodes_geo WHERE type = 'town')
                UNION
                (SELECT id, 200 as importance FROM nodes_geo WHERE type = 'village')
                UNION
                (SELECT from_id AS 'id', count(*) * 1000 as importance FROM routes GROUP BY from_id)
                UNION
                (SELECT to_id AS 'id', count(*) * 1000 as importance FROM routes GROUP BY to_id)
                UNION
                (SELECT node_id AS 'id', count(*) * 10 as importance FROM route_nodes GROUP BY node_id)
                ) as calc
            GROUP BY id;
        ";
        $nodes = DB::select(DB::raw($query), array());
        foreach ($nodes as $node) {
            NodeGeo::where('id', $node->id)->update(['importance' => $node->importance]);
        }
    }
}
