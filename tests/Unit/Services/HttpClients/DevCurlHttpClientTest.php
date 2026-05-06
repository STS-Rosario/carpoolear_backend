<?php

namespace Tests\Unit\Services\HttpClients;

use STS\Services\HttpClients\DevCurlHttpClient;
use Tests\TestCase;

class DevCurlHttpClientTest extends TestCase
{
    public function test_open_connection_runs_parent_and_property_probe_without_throwing(): void
    {
        if (! class_exists(\Facebook\HttpClients\FacebookCurlHttpClient::class)) {
            $this->markTestSkipped('Facebook PHP SDK (facebook/graph-sdk) is not installed.');
        }

        $client = new DevCurlHttpClient;
        $client->openConnection('https://example.test/', 'GET', '', [], 5);

        $this->assertTrue(true);
    }
}
