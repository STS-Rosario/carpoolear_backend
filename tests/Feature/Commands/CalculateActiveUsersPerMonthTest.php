<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\User;
use Tests\TestCase;

class CalculateActiveUsersPerMonthTest extends TestCase
{
    private Carbon $targetMonth;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
        $this->targetMonth = Carbon::create(2026, 3, 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function monthOption(): string
    {
        return $this->targetMonth->format('Y-m');
    }

    public function test_calculates_active_users_for_previous_month()
    {
        $monthStr = $this->monthOption();

        // Active user with connection last month
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(5),
        ]);

        // Another active user
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(10),
        ]);

        // Inactive user (should not count)
        User::factory()->create([
            'active' => false,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(3),
        ]);

        // Banned user (should not count)
        User::factory()->create([
            'active' => true,
            'banned' => true,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(3),
        ]);

        // User with connection outside the target month (should not count)
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->subMonths(2),
        ]);

        $this->artisan('users:calculate-active-per-month', ['--month' => $monthStr])
            ->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($this->targetMonth->year, $this->targetMonth->month)->first();
        $this->assertNotNull($record);
        $this->assertEquals(2, $record->value);
    }

    public function test_dry_run_does_not_save_to_database()
    {
        $monthStr = $this->monthOption();

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(5),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => $monthStr,
            '--dry-run' => true,
        ])->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($this->targetMonth->year, $this->targetMonth->month)->first();
        $this->assertNull($record);
    }

    public function test_skips_if_data_already_exists()
    {
        $monthStr = $this->monthOption();

        ActiveUsersPerMonth::create([
            'year' => $this->targetMonth->year,
            'month' => $this->targetMonth->month,
            'saved_at' => Carbon::now(),
            'value' => 99,
        ]);

        $this->artisan('users:calculate-active-per-month', ['--month' => $monthStr])
            ->assertSuccessful();

        // Original value should be preserved
        $record = ActiveUsersPerMonth::forYearMonth($this->targetMonth->year, $this->targetMonth->month)->first();
        $this->assertEquals(99, $record->value);
    }

    public function test_force_recalculates()
    {
        $monthStr = $this->monthOption();

        ActiveUsersPerMonth::create([
            'year' => $this->targetMonth->year,
            'month' => $this->targetMonth->month,
            'saved_at' => Carbon::now(),
            'value' => 99,
        ]);

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $this->targetMonth->copy()->startOfMonth()->addDays(5),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => $monthStr,
            '--force' => true,
        ])->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($this->targetMonth->year, $this->targetMonth->month)->first();
        $this->assertNotEquals(99, $record->value);
    }
}
