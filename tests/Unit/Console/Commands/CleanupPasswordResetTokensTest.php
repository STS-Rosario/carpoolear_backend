<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Console\Commands\CleanupPasswordResetTokens;
use Tests\TestCase;

class CleanupPasswordResetTokensTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_handle_deletes_only_tokens_older_than_selected_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 45, 0));

        DB::table('password_resets')->insert([
            [
                'email' => 'old@example.com',
                'token' => 'old-token',
                'created_at' => Carbon::now()->subHours(30)->toDateTimeString(),
            ],
            [
                'email' => 'recent@example.com',
                'token' => 'recent-token',
                'created_at' => Carbon::now()->subHours(5)->toDateTimeString(),
            ],
        ]);

        $this->artisan('auth:cleanup-reset-tokens', ['--hours' => 24])
            ->expectsOutput('Cleaning up password reset tokens older than 24 hours...')
            ->expectsOutput('Deleted 1 expired password reset tokens.')
            ->assertExitCode(0);

        $this->assertSame(0, DB::table('password_resets')->where('token', 'old-token')->count());
        $this->assertSame(1, DB::table('password_resets')->where('token', 'recent-token')->count());
    }

    public function test_command_signature_description_and_default_hours_are_defined(): void
    {
        $command = new CleanupPasswordResetTokens;

        $this->assertSame('auth:cleanup-reset-tokens', $command->getName());
        $this->assertStringContainsString('Clean up expired password reset tokens', $command->getDescription());
        $this->assertSame('hours', $command->getDefinition()->getOption('hours')->getName());
        $this->assertSame(24, (int) $command->getDefinition()->getOption('hours')->getDefault());
    }
}
