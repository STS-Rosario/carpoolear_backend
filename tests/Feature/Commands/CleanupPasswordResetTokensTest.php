<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CleanupPasswordResetTokensTest extends TestCase
{
    use DatabaseTransactions;

    public function testDeletesExpiredTokens()
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

    public function testKeepsAllTokensWhenNoneExpired()
    {
        DB::table('password_resets')->insert([
            'email' => 'fresh@example.com',
            'token' => 'fresh-token',
            'created_at' => Carbon::now()->subHours(2),
        ]);

        $this->artisan('auth:cleanup-reset-tokens')->assertSuccessful();

        $this->assertDatabaseHas('password_resets', ['email' => 'fresh@example.com']);
    }

    public function testCustomHoursOption()
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
