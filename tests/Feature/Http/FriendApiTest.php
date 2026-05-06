<?php

namespace Tests\Feature\Http;

use Mockery as m;
use Tests\TestCase;

class FriendApiTest extends TestCase
{
    protected $friendsLogic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->friendsLogic = $this->mock(\STS\Services\Logic\FriendsManager::class);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_request()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('request')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/request/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function test_accept()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('accept')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/accept/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function test_reject()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('reject')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/reject/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function test_delete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/delete/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function test_index()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('getFriends')->once()->andReturn(
            new \Illuminate\Database\Eloquent\Collection([$u1])
        );

        $response = $this->call('GET', 'api/friends/');
        $this->assertTrue($response->status() == 200);
        $friends = $this->parseJson($response);

        $this->assertTrue($friends->data[0]->id == $u1->id);
    }

    public function test_pendings()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('getPendings')->once()->andReturn(
            new \Illuminate\Database\Eloquent\Collection([$u1, $u2])
        );

        $response = $this->call('GET', 'api/friends/pedings');
        $this->assertTrue($response->status() == 200);
        $friends = $this->parseJson($response);

        $this->assertTrue(count($friends->data) == 2);
    }
}
