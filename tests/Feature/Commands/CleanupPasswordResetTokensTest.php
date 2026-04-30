<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupPasswordResetTokensTest extends TestCase
{
    public function test_deletes_expired_tokens()
    {
        DB::table('password_resets')->insert([
            'email' => 'old@example.com',
            'token' => 'old-token',
            'created_at' => Carbon::now()->subHours(25),
        ]);

        DB::table('password_resets')->insert([
            'email' => 'recent@example.com',
            'token' => 'recent-token',
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $this->artisan('auth:cleanup-reset-tokens')->assertSuccessful();

        $this->assertDatabaseMissing('password_resets', ['email' => 'old@example.com']);
        $this->assertDatabaseHas('password_resets', ['email' => 'recent@example.com']);
    }

    public function test_keeps_all_tokens_when_none_expired()
    {
        DB::table('password_resets')->insert([
            'email' => 'fresh@example.com',
            'token' => 'fresh-token',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $this->artisan('auth:cleanup-reset-tokens')->assertSuccessful();

        $this->assertDatabaseHas('password_resets', ['email' => 'fresh@example.com']);
    }

    public function test_custom_hours_option()
    {
        DB::table('password_resets')->insert([
            'email' => 'test@example.com',
            'token' => 'test-token',
            'created_at' => Carbon::now()->subHours(5),
        ]);

        $this->artisan('auth:cleanup-reset-tokens', ['--hours' => 4])->assertSuccessful();

        $this->assertDatabaseMissing('password_resets', ['email' => 'test@example.com']);
    }
}
