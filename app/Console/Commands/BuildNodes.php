<?php

namespace STS\Console\Commands;

use STS\User;
use Carbon\Carbon;
use STS\Entities\NodeGeo;
use Illuminate\Console\Command;
use Storage;


class BuildNodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nodesgeo:load';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate nodes table from json file';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $path = storage_path() . "/export.geojson";
        $this->json = json_decode(file_get_contents($path), true); 
        $this->nodes = $this->json['features'];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach ($this->nodes as $feature) {
         
            $props = $feature['properties'];
            $geo = $feature['geometry'];
            if (isset($props['name'])){
                $node = new NodeGeo;
                $node->name = $props['name'];
                $node->type = $props['place'];
                $node->lat = $geo['coordinates'][0];
                $node->lng = $geo['coordinates'][1];
                $node->save();
            }
        }
    }
}
