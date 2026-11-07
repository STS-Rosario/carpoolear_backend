<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\NodeGeo;

class BackfillNodeGeoAltNames extends Command
{
    protected $signature = 'nodesgeo:backfill-alt-names {--geojson-dir= : Directory containing *.geojson place files}';

    protected $description = 'Populate nodes_geo.alt_name from geojson alt_name properties';

    public string $dir;

    public function __construct()
    {
        parent::__construct();
        $this->dir = storage_path().'/geojson/';
    }

    public function handle(): int
    {
        $updated = 0;
        $dir = rtrim((string) ($this->option('geojson-dir') ?: $this->dir), '/').'/';
        $files = array_filter(scandir($dir) ?: [], function ($file) {
            return str_ends_with($file, '.geojson');
        });

        foreach ($files as $file) {
            $country = pathinfo($file, PATHINFO_FILENAME);
            $json = json_decode(file_get_contents($dir.$file), true);
            if (! is_array($json) || empty($json['features'])) {
                continue;
            }

            foreach ($json['features'] as $feature) {
                $props = $feature['properties'] ?? [];
                if (empty($props['name']) || empty($props['alt_name'])) {
                    continue;
                }
                $coords = $feature['geometry']['coordinates'] ?? null;
                if (! is_array($coords) || count($coords) < 2) {
                    continue;
                }
                $lng = (float) $coords[0];
                $lat = (float) $coords[1];
                $epsilon = 0.001;

                $affected = NodeGeo::query()
                    ->where('country', $country)
                    ->where('name', $props['name'])
                    ->whereBetween('lat', [$lat - $epsilon, $lat + $epsilon])
                    ->whereBetween('lng', [$lng - $epsilon, $lng + $epsilon])
                    ->update(['alt_name' => $props['alt_name']]);

                $updated += $affected;
            }
        }

        $this->info("Updated alt_name on {$updated} nodes.");

        return self::SUCCESS;
    }
}
