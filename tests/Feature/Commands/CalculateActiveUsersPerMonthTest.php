<?php

namespace Tests\Feature\Commands;

use Carbon\Carbon;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\User;
use Tests\TestCase;

class CalculateActiveUsersPerMonthTest extends TestCase
{
    public function test_calculates_active_users_for_previous_month()
    {
        $lastMonth = Carbon::now()->subMonth();
        $monthStr = $lastMonth->format('Y-m');

        // Active user with connection last month
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(5),
        ]);

        // Another active user
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(10),
        ]);

        // Inactive user (should not count)
        User::factory()->create([
            'active' => false,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(3),
        ]);

        // Banned user (should not count)
        User::factory()->create([
            'active' => true,
            'banned' => true,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(3),
        ]);

        // User with connection outside the target month (should not count)
        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->subMonths(2),
        ]);

        $this->artisan('users:calculate-active-per-month', ['--month' => $monthStr])
            ->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($lastMonth->year, $lastMonth->month)->first();
        $this->assertNotNull($record);
        $this->assertEquals(2, $record->value);
    }

    public function test_dry_run_does_not_save_to_database()
    {
        $lastMonth = Carbon::now()->subMonth();
        $monthStr = $lastMonth->format('Y-m');

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(5),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => $monthStr,
            '--dry-run' => true,
        ])->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($lastMonth->year, $lastMonth->month)->first();
        $this->assertNull($record);
    }

    public function test_skips_if_data_already_exists()
    {
        $lastMonth = Carbon::now()->subMonth();
        $monthStr = $lastMonth->format('Y-m');

        ActiveUsersPerMonth::create([
            'year' => $lastMonth->year,
            'month' => $lastMonth->month,
            'saved_at' => Carbon::now(),
            'value' => 99,
        ]);

        $this->artisan('users:calculate-active-per-month', ['--month' => $monthStr])
            ->assertSuccessful();

        // Original value should be preserved
        $record = ActiveUsersPerMonth::forYearMonth($lastMonth->year, $lastMonth->month)->first();
        $this->assertEquals(99, $record->value);
    }

    public function test_force_recalculates()
    {
        $lastMonth = Carbon::now()->subMonth();
        $monthStr = $lastMonth->format('Y-m');

        ActiveUsersPerMonth::create([
            'year' => $lastMonth->year,
            'month' => $lastMonth->month,
            'saved_at' => Carbon::now(),
            'value' => 99,
        ]);

        User::factory()->create([
            'active' => true,
            'banned' => false,
            'last_connection' => $lastMonth->copy()->startOfMonth()->addDays(5),
        ]);

        $this->artisan('users:calculate-active-per-month', [
            '--month' => $monthStr,
            '--force' => true,
        ])->assertSuccessful();

        $record = ActiveUsersPerMonth::forYearMonth($lastMonth->year, $lastMonth->month)->first();
        $this->assertNotEquals(99, $record->value);
    }
}
