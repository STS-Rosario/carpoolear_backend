<?php

namespace Tests\Unit\Services\Social;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Services\Social\FacebookSocialProvider;
use Tests\TestCase;

class FacebookSocialProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_user_data_maps_profile_transforms_gender_and_birthday_on_200(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::on(function (mixed $message): bool {
                return is_string($message)
                    && str_starts_with($message, 'FACEBOOK BODY: ')
                    && str_contains($message, '"id":"fb-1"');
            }));

        $body = [
            'id' => 'fb-1',
            'email' => 'fb@example.test',
            'name' => 'Face Book',
            'gender' => 'male',
            'picture' => ['data' => ['url' => 'https://cdn.example/p.png']],
            'birthday' => '03/21/1988',
        ];

        $http = Mockery::mock(Client::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function (string $method, string $uri): bool {
                return $method === 'GET'
                    && str_starts_with($uri, 'https://graph.facebook.com/v3.3/me?fields=')
                    && str_contains($uri, '&access_token=my-token');
            })
            ->andReturn(new Response(200, [], json_encode($body)));

        $provider = new FacebookSocialProvider('my-token');
        $this->injectClient($provider, $http);

        $row = $provider->getUserData([]);

        $this->assertSame('fb-1', $row['provider_user_id']);
        $this->assertSame('fb@example.test', $row['email']);
        $this->assertSame('Masculino', $row['gender']);
        $this->assertSame('1988-03-21', $row['birthday']);
        $this->assertSame('https://cdn.example/p.png', $row['image']);
    }

    public function test_get_user_data_sets_error_and_null_when_status_not_200(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('request')
            ->once()
            ->andReturn(new Response(401, [], '{"error":"nope"}'));

        $provider = new FacebookSocialProvider('bad');
        $this->injectClient($provider, $http);

        $this->assertNull($provider->getUserData([]));
        $this->assertSame(['error' => 'Error obteniendo el perfil'], $provider->getError());
    }

    public function test_get_user_friends_returns_ids_on_200(): void
    {
        $payload = json_encode(['data' => [['id' => 'a'], ['id' => 'b']]]);

        $http = Mockery::mock(Client::class);
        $http->shouldReceive('request')
            ->once()
            ->withArgs(function (string $method, string $uri): bool {
                return $method === 'GET'
                    && str_contains($uri, 'https://graph.facebook.com/v3.3/me/friends?limit=5000')
                    && str_contains($uri, '&access_token=t2');
            })
            ->andReturn(new Response(200, [], $payload));

        $provider = new FacebookSocialProvider('t2');
        $this->injectClient($provider, $http);

        $this->assertSame(['a', 'b'], $provider->getUserFriends());
    }

    public function test_get_user_friends_sets_error_on_non_200(): void
    {
        $http = Mockery::mock(Client::class);
        $http->shouldReceive('request')
            ->once()
            ->andReturn(new Response(500, [], '{}'));

        $provider = new FacebookSocialProvider('t3');
        $this->injectClient($provider, $http);

        $this->assertSame([], $provider->getUserFriends());
        $this->assertSame(['error' => 'Error obteniendo amistades'], $provider->getError());
    }

    private function injectClient(FacebookSocialProvider $provider, Client $client): void
    {
        $bind = Closure::bind(function (Client $c): void {
            $this->client = $c;
        }, $provider, FacebookSocialProvider::class);
        $bind($client);
    }
}
