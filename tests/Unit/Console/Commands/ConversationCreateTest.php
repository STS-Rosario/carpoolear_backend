<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Mockery;
use STS\Models\User;
use STS\Services\Logic\ConversationsManager;
use Tests\TestCase;

class ConversationCreateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_creates_private_conversation_and_reports_success(): void
    {
        Event::fake([MessageLogged::class]);

        $from = User::factory()->create();
        $to = User::factory()->create();

        $manager = Mockery::mock(ConversationsManager::class);
        $manager->shouldReceive('findOrCreatePrivateConversation')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user instanceof User && $user->id === $from->id),
                Mockery::on(fn ($user) => $user instanceof User && $user->id === $to->id)
            )
            ->andReturn((object) ['id' => 123]);
        $this->app->instance(ConversationsManager::class, $manager);

        $this->artisan('conversation:create', [
            'from' => $from->id,
            'to' => $to->id,
        ])
            ->expectsOutput('Conversation has been created.')
            ->assertExitCode(0);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND ConversationCreate';
        });
    }

    public function test_handle_reports_error_when_conversation_cannot_be_created(): void
    {
        Event::fake([MessageLogged::class]);

        $from = User::factory()->create();
        $to = User::factory()->create();

        $manager = Mockery::mock(ConversationsManager::class);
        $manager->shouldReceive('findOrCreatePrivateConversation')
            ->once()
            ->andReturn(null);
        $this->app->instance(ConversationsManager::class, $manager);

        $this->artisan('conversation:create', [
            'from' => $from->id,
            'to' => $to->id,
        ])
            ->expectsOutput('Conversation could not be created, maybe none of the users are admin?')
            ->assertExitCode(0);

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $e): bool => $e->message === 'COMMAND ConversationCreate');
    }
}
