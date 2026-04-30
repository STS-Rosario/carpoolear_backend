<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use STS\Models\User;
use STS\Notifications\DummyNotification;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('api/notifications')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_notification_count_requires_authentication(): void
    {
        $this->getJson('api/notifications/count')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_delete_notification_requires_authentication(): void
    {
        $this->deleteJson('api/notifications/1')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_index_returns_notification_list_envelope(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-10 10:00:00');
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'api');
        $dummy->notify($user);

        $this->actingAs($user, 'api');

        $response = $this->getJson('api/notifications');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'readed',
                    'created_at',
                    'text',
                    'extras',
                ],
            ],
        ]);
        $response->assertJsonPath('data.0.text', 'Dummy Notification api');

        Carbon::setTestNow();
    }

    public function test_count_returns_unread_total(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-11 09:00:00');
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($user, 'api');
        $this->getJson('api/notifications/count')->assertOk()->assertJson(['data' => 0]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'unread');
        $dummy->notify($user);

        $this->getJson('api/notifications/count')->assertOk()->assertJson(['data' => 1]);

        Carbon::setTestNow();
    }

    public function test_delete_known_notification_returns_ok_envelope(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-12 12:00:00');
        $user = User::factory()->create(['active' => true, 'banned' => false]);

        $dummy = new DummyNotification;
        $dummy->setAttribute('dummy', 'del');
        $dummy->notify($user);

        $rows = $user->notifications()->orderByDesc('id')->get();
        $this->assertCount(1, $rows);
        $id = (int) $rows->first()->id;

        $this->actingAs($user, 'api');

        $this->deleteJson('api/notifications/'.$id)
            ->assertOk()
            ->assertExactJson(['data' => 'ok']);

        $this->assertCount(0, $user->fresh()->notifications);

        Carbon::setTestNow();
    }

    public function test_delete_unknown_notification_is_unprocessable(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $this->actingAs($user, 'api');

        $this->deleteJson('api/notifications/999999999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Could not delete notiication.');
    }
}
