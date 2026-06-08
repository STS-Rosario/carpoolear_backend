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

    public function test_lists_all_changelogs_sorted_by_semver_newest_first(): void
    {
        Changelog::create([
            'version' => '1.0.0',
            'body_markdown' => 'Primera versión',
        ]);
        Changelog::create([
            'version' => '2.10.0',
            'body_markdown' => 'Mejoras mayores',
        ]);
        Changelog::create([
            'version' => '2.2.0',
            'body_markdown' => 'Parches',
        ]);

        $response = $this->getJson('api/changelogs')->assertOk();

        $versions = array_column($response->json('data'), 'version');
        $this->assertSame(['2.10.0', '2.2.0', '1.0.0'], $versions);
    }
}
