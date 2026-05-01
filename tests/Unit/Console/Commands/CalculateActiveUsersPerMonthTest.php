<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use STS\Console\Commands\CalculateActiveUsersPerMonth;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\User;
use Tests\TestCase;

class CalculateActiveUsersPerMonthTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dry_run_reports_count_without_persisting_data(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::create(2026, 3, 10, 11, 0, 0),
        ]);
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::create(2026, 2, 10, 11, 0, 0),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => '2026-03',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Starting active users calculation...')
            ->expectsOutputToContain('Calculating active users for: March 2026')
            ->expectsOutputToContain('Found 1 active users for March 2026')
            ->expectsOutput('DRY RUN: Would save the following data:')
            ->expectsOutput('  Year: 2026')
            ->expectsOutput('  Month: 3')
            ->expectsOutput('  Value: 1')
            ->assertExitCode(0);

        $this->assertSame(0, ActiveUsersPerMonth::query()->where('year', 2026)->where('month', 3)->count());
    }

    public function test_existing_data_without_force_warns_and_keeps_existing_value(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));

        ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 3,
            'saved_at' => Carbon::now()->subDay(),
            'value' => 99,
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => '2026-03',
        ])
            ->expectsOutputToContain('Data already exists for March 2026. Use --force to recalculate.')
            ->assertExitCode(0);

        $this->assertSame(
            99,
            (int) ActiveUsersPerMonth::query()->where('year', 2026)->where('month', 3)->value('value')
        );
    }

    public function test_force_recalculation_replaces_existing_row_with_fresh_count(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));

        ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 3,
            'saved_at' => Carbon::now()->subDay(),
            'value' => 50,
        ]);

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::create(2026, 3, 2, 8, 0, 0),
        ]);
        User::factory()->create([
            'active' => true,
            'banned' => true,
            'last_connection' => Carbon::create(2026, 3, 3, 8, 0, 0),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => '2026-03',
            '--force' => true,
        ])
            ->expectsOutputToContain('Found 1 active users for March 2026')
            ->expectsOutputToContain('Data saved successfully for March 2026')
            ->expectsOutput('Active users calculation completed successfully!')
            ->assertExitCode(0);

        $this->assertSame(1, ActiveUsersPerMonth::query()->where('year', 2026)->where('month', 3)->count());
        $this->assertSame(
            1,
            (int) ActiveUsersPerMonth::query()->where('year', 2026)->where('month', 3)->value('value')
        );
    }

    public function test_command_contract_exposes_expected_signature_and_description(): void
    {
        $command = new CalculateActiveUsersPerMonth;

        $this->assertSame('users:calculate-active-per-month', $command->getName());
        $this->assertStringContainsString('Calculate and store the number of active users', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('month'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function test_invalid_month_option_returns_non_zero_exit_code(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));

        $this->artisan('users:calculate-active-per-month', [
            '--month' => 'not-a-month',
        ])
            ->expectsOutputToContain('Invalid month format')
            ->assertExitCode(1);
    }

    public function test_current_or_future_month_option_returns_non_zero_exit_code(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));

        $this->artisan('users:calculate-active-per-month', [
            '--month' => '2026-04',
        ])
            ->expectsOutputToContain('Cannot calculate active users for current month')
            ->expectsOutputToContain('Current month data is incomplete')
            ->expectsOutputToContain('Please specify a past month')
            ->assertExitCode(1);
    }

    public function test_successful_save_persists_saved_at_and_writes_summary_log(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
        Event::fake([MessageLogged::class]);

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => Carbon::create(2026, 3, 5, 9, 0, 0),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => '2026-03',
        ])
            ->expectsOutput('Active users calculation completed successfully!')
            ->assertExitCode(0);

        $row = ActiveUsersPerMonth::query()->where('year', 2026)->where('month', 3)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->saved_at);
        $this->assertTrue($row->saved_at->isSameDay(Carbon::now()));

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $log): bool => str_contains(
            (string) $log->message,
            'Active users calculated for March 2026: 1 users'
        ));
    }
}
