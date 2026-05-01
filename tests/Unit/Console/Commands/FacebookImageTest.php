<?php

namespace Tests\Unit\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Console\Commands\FacebookImage;
use STS\Models\SocialAccount;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\FileRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class FacebookImageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_outputs_zero_when_no_users_match_trip_and_account_filters(): void
    {
        Event::fake([MessageLogged::class]);
        User::factory()->create();

        $this->artisan('user:facebook')
            ->expectsOutput('0')
            ->assertExitCode(0);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND FacebookImage';
        });
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new FacebookImage(new FileRepository);

        $this->assertSame('user:facebook', $command->getName());
        $this->assertStringContainsString('Download profile images facebook from users', $command->getDescription());
    }

    public function test_requests_graph_url_downloads_image_and_persists_profile_path(): void
    {
        Event::fake([MessageLogged::class]);

        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        Trip::factory()->create(['user_id' => $user->id]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'fb-graph-user-99',
            'provider' => 'facebook',
        ]);

        $jpegBytes = random_bytes(64);
        $dataUrl = 'data:image/jpeg;base64,'.base64_encode($jpegBytes);

        $graphJson = json_encode(['data' => ['url' => $dataUrl]], JSON_THROW_ON_ERROR);
        $graphResponse = new Response(200, [], $graphJson);

        $expectedUri = 'https://graph.facebook.com/v3.3/fb-graph-user-99/picture?redirect=0&height=200&width=200&type=normal';

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->with('GET', $expectedUri)
            ->andReturn($graphResponse);

        $files = Mockery::mock(FileRepository::class);
        $files->shouldReceive('createFromData')
            ->once()
            ->withArgs(function (string $data, string $ext, string $folder) use ($jpegBytes): bool {
                return $data === $jpegBytes
                    && $ext === 'jpg'
                    && $folder === 'image/profile/';
            })
            ->andReturn('stored-face.jpg');

        $this->app->bind(FacebookImage::class, fn () => new FacebookImage($files, $client));

        $command = $this->app->make(FacebookImage::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('1', $display);
        $this->assertStringContainsString('Ada Lovelace', $display);
        $this->assertStringContainsString('base64,', $display);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND FacebookImage';
        });

        $user->refresh();
        $this->assertSame('stored-face.jpg', $user->image);
    }

    public function test_does_not_call_graph_when_user_only_has_past_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id' => $user->id,
            'trip_date' => now()->subDays(5)->toDateTimeString(),
        ]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'fb-ignored',
            'provider' => 'facebook',
        ]);

        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('request');

        $files = Mockery::mock(FileRepository::class);
        $files->shouldNotReceive('createFromData');

        $this->app->bind(FacebookImage::class, fn () => new FacebookImage($files, $client));

        $this->artisan('user:facebook')
            ->expectsOutput('0')
            ->assertSuccessful();
    }

    public function test_outputs_no_account_when_social_row_has_no_provider_user_id(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => '',
            'provider' => 'facebook',
        ]);

        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('request');

        $files = Mockery::mock(FileRepository::class);
        $files->shouldNotReceive('createFromData');

        $this->app->bind(FacebookImage::class, fn () => new FacebookImage($files, $client));

        $this->artisan('user:facebook')
            ->expectsOutput('1')
            ->expectsOutput('No account')
            ->assertSuccessful();
    }

    public function test_skips_download_when_graph_returns_non_200(): void
    {
        $user = User::factory()->create(['name' => 'Grace Hopper']);
        $imageBefore = $user->fresh()->image;
        Trip::factory()->create(['user_id' => $user->id]);
        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider_user_id' => 'fb-429',
            'provider' => 'facebook',
        ]);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('request')
            ->once()
            ->andReturn(new Response(429, [], ''));

        $files = Mockery::mock(FileRepository::class);
        $files->shouldNotReceive('createFromData');

        $this->app->bind(FacebookImage::class, fn () => new FacebookImage($files, $client));

        $this->artisan('user:facebook')
            ->expectsOutput('1')
            ->doesntExpectOutputToContain('Grace Hopper')
            ->assertSuccessful();

        $user->refresh();
        $this->assertSame($imageBefore, $user->image);
    }
}
