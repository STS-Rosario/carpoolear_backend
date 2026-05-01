<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\BannedUser;
use STS\Models\User;
use Tests\TestCase;

class BannedUserTest extends TestCase
{
    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();

        $row = BannedUser::query()->create([
            'user_id' => $user->id,
            'nro_doc' => '30123456',
            'banned_at' => '2026-02-15 14:00:00',
            'banned_by' => $admin->id,
            'note' => 'Terms violation (test).',
        ]);

        $this->assertTrue($row->fresh()->user->is($user));
    }

    public function test_banned_at_casts_to_carbon(): void
    {
        $user = User::factory()->create();

        $row = BannedUser::query()->create([
            'user_id' => $user->id,
            'nro_doc' => null,
            'banned_at' => '2026-03-10 08:30:00',
            'banned_by' => 0,
            'note' => null,
        ]);

        $row = $row->fresh();
        $this->assertInstanceOf(Carbon::class, $row->banned_at);
        $this->assertSame('2026-03-10 08:30:00', $row->banned_at->format('Y-m-d H:i:s'));
    }

    public function test_persists_nro_doc_banned_by_and_note(): void
    {
        $user = User::factory()->create();

        $row = BannedUser::query()->create([
            'user_id' => $user->id,
            'nro_doc' => '27111222',
            'banned_at' => now(),
            'banned_by' => 99,
            'note' => 'Manual ban from tests.',
        ]);

        $row = $row->fresh();
        $this->assertSame('27111222', $row->nro_doc);
        $this->assertSame(99, (int) $row->banned_by);
        $this->assertSame('Manual ban from tests.', $row->note);
    }

    public function test_allows_null_nro_doc_and_note(): void
    {
        $user = User::factory()->create();

        $row = BannedUser::query()->create([
            'user_id' => $user->id,
            'nro_doc' => null,
            'banned_at' => now(),
            'banned_by' => 0,
            'note' => null,
        ]);

        $row = $row->fresh();
        $this->assertNull($row->nro_doc);
        $this->assertNull($row->note);
    }

    public function test_table_name_is_banned_users(): void
    {
        $this->assertSame('banned_users', (new BannedUser)->getTable());
    }
}
