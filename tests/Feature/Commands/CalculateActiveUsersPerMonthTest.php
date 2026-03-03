<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use STS\Models\User;
use STS\Models\ActiveUsersPerMonth;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CalculateActiveUsersPerMonthTest extends TestCase
{
    use DatabaseTransactions;

    public function testCalculatesActiveUsersForPreviousMonth()
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

    public function testDryRunDoesNotSaveToDatabase()
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

    public function testSkipsIfDataAlreadyExists()
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

    public function testForceRecalculates()
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
