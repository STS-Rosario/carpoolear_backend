<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Mockery as m;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FriendApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $friendsLogic;

    public function setUp(): void
    {
        parent::setUp();
        $this->friendsLogic = $this->mock(\STS\Services\Logic\FriendsManager::class);
    }

    public function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function testRequest()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('request')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/request/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testAccept()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('accept')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/accept/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testReject()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('reject')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/reject/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testDelete()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('delete')->once()->andReturn(true);

        $response = $this->call('POST', 'api/friends/delete/'.$u2->id);
        $this->assertTrue($response->status() == 200);
    }

    public function testIndex()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('getFriends')->once()->andReturn(\STS\Models\User::all());

        $response = $this->call('GET', 'api/friends/');
        $this->assertTrue($response->status() == 200);
        $friends = $this->parseJson($response);

        $this->assertTrue($friends->data[0]->id == $u1->id);
    }

    public function testPendings()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $this->actingAs($u1, 'api');

        $this->friendsLogic->shouldReceive('getPendings')->once()->andReturn(\STS\Models\User::all());

        $response = $this->call('GET', 'api/friends/pedings');
        $this->assertTrue($response->status() == 200);
        $friends = $this->parseJson($response);

        $this->assertTrue(count($friends->data) == 2);
    }
}
