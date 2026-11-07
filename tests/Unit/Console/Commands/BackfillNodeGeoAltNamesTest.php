<?php

namespace Tests\Unit\Console\Commands;

use STS\Models\NodeGeo;
use Tests\TestCase;

class BackfillNodeGeoAltNamesTest extends TestCase
{
    public function test_handle_updates_alt_name_from_geojson_by_name_and_coordinates(): void
    {
        $tmpDir = sys_get_temp_dir().'/backfill_alt_'.uniqid().'/';
        mkdir($tmpDir, 0777, true);

        file_put_contents($tmpDir.'ARG.geojson', json_encode([
            'features' => [[
                'properties' => [
                    'name' => 'Villa Las Rosas',
                    'alt_name' => 'Villa de Las Rosas',
                ],
                'geometry' => [
                    'coordinates' => [-65.0520865, -31.9471009],
                ],
            ]],
        ]));

        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'Villa Las Rosas',
            'lat' => -31.9471009,
            'lng' => -65.0520865,
            'type' => 'village',
            'state' => 'Córdoba',
            'country' => 'ARG',
            'importance' => 200,
        ])->save();

        $this->artisan('nodesgeo:backfill-alt-names', ['--geojson-dir' => $tmpDir])
            ->assertSuccessful();

        $node->refresh();
        $this->assertSame('Villa de Las Rosas', $node->alt_name);
    }
}
