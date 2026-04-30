<?php

namespace Tests\Feature\Http;

use STS\Models\NodeGeo;
use Tests\TestCase;

class RoutesApiTest extends TestCase
{
    private function seedNode(string $nameSubstring, string $country, int $importance = 5): NodeGeo
    {
        $node = new NodeGeo;
        $node->forceFill([
            'name' => 'Place '.$nameSubstring.' City',
            'lat' => -31.4,
            'lng' => -64.2,
            'type' => 'city',
            'state' => 'TestState',
            'country' => $country,
            'importance' => $importance,
        ])->save();

        return $node->fresh();
    }

    private function autocompleteUrl(array $query): string
    {
        return 'api/trips/autocomplete?'.http_build_query($query);
    }

    public function test_autocomplete_is_reachable_without_authentication(): void
    {
        $needle = 'ac_public_'.uniqid('', true);
        $this->seedNode($needle, 'ARG', 9);

        $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'country' => 'ARG',
            'multicountry' => 'false',
        ]))
            ->assertOk()
            ->assertJsonStructure(['nodes_geos'])
            ->assertJsonPath('nodes_geos.0.country', 'ARG');
    }

    public function test_autocomplete_without_required_query_returns_empty_nodes(): void
    {
        $this->getJson($this->autocompleteUrl([
            'multicountry' => 'false',
        ]))
            ->assertOk()
            ->assertExactJson(['nodes_geos' => []]);
    }

    public function test_autocomplete_defaults_country_to_arg_when_country_omitted(): void
    {
        $needle = 'ac_default_'.uniqid('', true);
        $this->seedNode($needle, 'ARG', 8);

        $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'multicountry' => 'false',
        ]))
            ->assertOk()
            ->assertJsonPath('nodes_geos.0.country', 'ARG');
    }

    public function test_autocomplete_with_multicountry_false_filters_by_country(): void
    {
        $needle = 'ac_single_'.uniqid('', true);
        $argNode = $this->seedNode($needle, 'ARG', 10);
        $this->seedNode($needle, 'BRA', 10);

        $payload = $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'country' => 'ARG',
            'multicountry' => 'false',
        ]))->assertOk()->json('nodes_geos');

        $this->assertIsArray($payload);
        $countries = array_unique(array_column($payload, 'country'));
        $this->assertContains('ARG', $countries);
        $this->assertNotContains('BRA', $countries);
        $ids = array_column($payload, 'id');
        $this->assertContains($argNode->id, $ids);
    }

    public function test_autocomplete_with_multicountry_true_does_not_restrict_country(): void
    {
        $needle = 'ac_multi_'.uniqid('', true);
        $argNode = $this->seedNode($needle, 'ARG', 7);
        $braNode = $this->seedNode($needle, 'BRA', 7);

        $payload = $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'country' => 'ARG',
            'multicountry' => 'true',
        ]))->assertOk()->json('nodes_geos');

        $this->assertIsArray($payload);
        $ids = array_column($payload, 'id');
        $this->assertContains($argNode->id, $ids);
        $this->assertContains($braNode->id, $ids);
    }

    public function test_autocomplete_non_true_multicountry_string_is_treated_as_single_country(): void
    {
        $needle = 'ac_str_'.uniqid('', true);
        $this->seedNode($needle, 'ARG', 6);
        $this->seedNode($needle, 'URY', 6);

        $payload = $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'country' => 'ARG',
            'multicountry' => 'false',
        ]))->assertOk()->json('nodes_geos');

        foreach ($payload as $row) {
            $this->assertSame('ARG', $row['country']);
        }
    }

    public function test_autocomplete_response_includes_expected_node_fields(): void
    {
        $needle = 'ac_shape_'.uniqid('', true);
        $this->seedNode($needle, 'ARG', 4);

        $this->getJson($this->autocompleteUrl([
            'name' => $needle,
            'country' => 'ARG',
            'multicountry' => 'false',
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'nodes_geos' => [
                    '*' => ['id', 'name', 'lat', 'lng', 'type', 'state', 'country'],
                ],
            ]);
    }
}
