<?php

namespace Tests\Feature\Http;

use STS\Models\Changelog;
use Tests\TestCase;

class ChangelogApiTest extends TestCase
{
    public function test_returns_changelog_for_matching_version(): void
    {
        Changelog::create([
            'version' => '3.2.3',
            'body_markdown' => '## Novedades\n- Mejoras de rendimiento',
        ]);

        $response = $this->getJson('api/changelog?version=3.2.3')->assertOk();

        $data = $response->json('data');
        $this->assertSame('3.2.3', $data['version']);
        $this->assertSame('## Novedades\n- Mejoras de rendimiento', $data['body_markdown']);
    }

    public function test_returns_404_when_no_changelog_for_version(): void
    {
        $this->getJson('api/changelog?version=9.9.9')->assertNotFound();
    }

    public function test_validates_version_parameter(): void
    {
        $this->getJson('api/changelog')->assertUnprocessable()
            ->assertJsonValidationErrors(['version']);
    }
}
