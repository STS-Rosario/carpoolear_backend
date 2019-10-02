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
        $this->dir = storage_path() . "/geojson/";
        $this->files = scandir($this->dir);
        unset($this->files[0]);
        unset($this->files[1]);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach ($this->files as $file) {
            $parts = pathinfo($file);
            $country = $parts['filename'];
            $this->info($country);
            $json = json_decode(file_get_contents($this->dir . $file), true); 
            $nodes = $json['features'];
            foreach ($nodes as $feature) {
                $props = $feature['properties'];
                $geo = $feature['geometry'];
                if (isset($props['name'])){
                    $node = new NodeGeo;
                    $node->name = $props['name'];
                    $node->type = $props['place'];
                    $node->lng = $geo['coordinates'][0];
                    $node->lat = $geo['coordinates'][1];
                    $node->country = $country;
                    $node->save();
                }
            }    
        }
    }
}
