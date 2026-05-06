<?php

namespace Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use STS\Http\Controllers\Api\v1\FriendsController;
use STS\Http\ExceptionWithErrors;
use STS\Models\User;
use STS\Services\Logic\FriendsManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class FriendsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new FriendsController(
            Request::create('/'),
            Mockery::mock(FriendsManager::class),
            Mockery::mock(UsersManager::class),
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
    }

    public function test_request_returns_ok_when_friend_exists_and_manager_succeeds(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('find')->once()->with($friend->id)->andReturn($friend);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('request')->once()->andReturn(true);

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->request(Request::create('/'), $friend->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getData());
    }

    public function test_request_throws_when_friend_missing(): void
    {
        $actor = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('find')->once()->andReturn(null);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('getErrors')->once()->andReturn(['friend' => ['missing']]);

        $this->actingAs($actor, 'api');
        $this->expectException(ExceptionWithErrors::class);

        (new FriendsController(Request::create('/'), $friends, $users))
            ->request(Request::create('/'), 999_999);
    }

    public function test_accept_returns_ok_when_friend_exists_and_manager_succeeds(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('find')->once()->andReturn($friend);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('accept')->once()->andReturn(true);

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->accept(Request::create('/'), $friend->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getData());
    }

    public function test_delete_returns_ok_when_friend_exists_and_manager_succeeds(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('find')->once()->andReturn($friend);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('delete')->once()->andReturn(true);

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->delete(Request::create('/'), $friend->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getData());
    }

    public function test_reject_returns_ok_when_friend_exists_and_manager_succeeds(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $users->shouldReceive('find')->once()->andReturn($friend);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('reject')->once()->andReturn(true);

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->reject(Request::create('/'), $friend->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getData());
    }

    public function test_index_uses_paginator_when_page_size_present(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $friends = Mockery::mock(FriendsManager::class);
        $paginator = new LengthAwarePaginator([$friend], 1, 5, 1, ['path' => 'http://localhost']);
        $friends->shouldReceive('getFriends')->once()->with($actor, ['page_size' => 5])->andReturn($paginator);

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->index(Request::create('/', 'GET', ['page_size' => 5]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('meta', $response->getData(true));
    }

    public function test_index_uses_collection_when_page_size_absent(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('getFriends')->once()->with($actor, [])->andReturn(collect([$friend]));

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->index(Request::create('/', 'GET', []));

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayNotHasKey('meta', $payload);
    }

    public function test_pedings_returns_collection_payload(): void
    {
        $actor = User::factory()->create();
        $pending = User::factory()->create();
        $users = Mockery::mock(UsersManager::class);
        $friends = Mockery::mock(FriendsManager::class);
        $friends->shouldReceive('getPendings')->once()->with($actor)->andReturn(collect([$pending]));

        $this->actingAs($actor, 'api');
        $response = (new FriendsController(Request::create('/'), $friends, $users))
            ->pedings(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $response->getData(true));
    }
}
