<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use STS\Console\Commands\EvaluateBadges;
use STS\Models\User;
use Tests\TestCase;

class EvaluateBadgesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dry_run_respects_user_filters_and_processes_matching_users(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 10, 0, 0));

        $matching = User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::now()->subDays(5),
        ]);
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::now()->subDays(60),
        ]);
        User::factory()->create([
            'active' => true,
            'banned' => true,
            'last_connection' => Carbon::now()->subDays(2),
        ]);

        $this->artisan('badges:evaluate', [
            '--dry-run' => true,
            '--active-only' => true,
            '--activity-days' => 30,
            '--user-ids' => (string) $matching->id,
            '--batch-size' => 50,
        ])
            ->expectsOutput('Starting badge evaluation...')
            ->expectsOutput('User Statistics:')
            ->expectsOutput('  Total users to evaluate: 1')
            ->expectsOutput('Badge evaluation completed!')
            ->assertExitCode(0);
    }

    public function test_non_dry_run_can_be_cancelled_on_confirmation_prompt(): void
    {
        User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);

        $this->artisan('badges:evaluate')
            ->expectsConfirmation('Proceed with badge evaluation?', 'no')
            ->expectsOutput('Badge evaluation cancelled.')
            ->assertExitCode(0);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new EvaluateBadges;

        $this->assertSame('badges:evaluate', $command->getName());
        $this->assertStringContainsString('Evaluate badges for users', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('user-ids'));
        $this->assertTrue($command->getDefinition()->hasOption('active-only'));
        $this->assertTrue($command->getDefinition()->hasOption('activity-days'));
        $this->assertTrue($command->getDefinition()->hasOption('batch-size'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
    }
}
