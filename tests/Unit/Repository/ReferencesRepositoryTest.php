<?php

namespace Tests\Unit\Repository;

use Mockery;
use STS\Models\References;
use STS\Models\User;
use STS\Repository\ReferencesRepository;
use Tests\TestCase;

class ReferencesRepositoryTest extends TestCase
{
    private function repo(): ReferencesRepository
    {
        return new ReferencesRepository;
    }

    public function test_create_persists_reference_and_returns_true(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = new References([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Reliable and punctual.',
        ]);

        $this->assertTrue($this->repo()->create($reference));

        $this->assertDatabaseHas('users_references', [
            'id' => $reference->id,
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Reliable and punctual.',
        ]);
        $this->assertSame($from->id, $reference->fresh()->from->id);
    }

    public function test_create_allows_multiple_rows_between_same_users(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $repo = $this->repo();

        $first = new References([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'First note',
        ]);
        $second = new References([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Second note',
        ]);

        $this->assertTrue($repo->create($first));
        $this->assertTrue($repo->create($second));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, References::query()
            ->where('user_id_from', $from->id)
            ->where('user_id_to', $to->id)
            ->count());
    }

    public function test_create_returns_false_when_save_fails(): void
    {
        $reference = Mockery::mock(References::class);
        $reference->shouldReceive('save')->once()->andReturn(false);

        $this->assertFalse($this->repo()->create($reference));
    }

    public function test_create_invokes_save(): void
    {
        // Mutation intent: preserve `return $reference->save()` (~9–11 RemoveMethodCall).
        $reference = Mockery::mock(References::class);
        $reference->shouldReceive('save')->once()->andReturn(true);

        $this->assertTrue($this->repo()->create($reference));
    }
}
