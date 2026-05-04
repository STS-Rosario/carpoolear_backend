<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Models\ActiveUsersPerMonth;
use Tests\TestCase;

class ActiveUsersPerMonthTest extends TestCase
{
    public function test_fillable_lists_aggregate_columns(): void
    {
        // Mutation intent: `RemoveArrayItem` on `getFillable()` (was uncovered on `$fillable` property lines ~12–16).
        $this->assertSame(
            ['year', 'month', 'saved_at', 'value'],
            (new ActiveUsersPerMonth)->getFillable()
        );
    }

    public function test_casts_cover_all_stored_scalar_columns(): void
    {
        // Mutation intent: `RemoveArrayItem` / `AlwaysReturnEmptyArray` on `casts()` (~21–26).
        $casts = (new ActiveUsersPerMonth)->getCasts();

        $this->assertSame('integer', $casts['year']);
        $this->assertSame('integer', $casts['month']);
        $this->assertSame('datetime', $casts['saved_at']);
        $this->assertSame('integer', $casts['value']);
    }

    public function test_integer_and_datetime_casts(): void
    {
        $row = ActiveUsersPerMonth::query()->create([
            'year' => '2026',
            'month' => '3',
            'saved_at' => '2026-04-01 00:00:00',
            'value' => '142',
        ]);

        $row = $row->fresh();
        $this->assertIsInt($row->year);
        $this->assertIsInt($row->month);
        $this->assertIsInt($row->value);
        $this->assertSame(2026, $row->year);
        $this->assertSame(3, $row->month);
        $this->assertSame(142, $row->value);
        $this->assertInstanceOf(Carbon::class, $row->saved_at);
        $this->assertSame('2026-04-01 00:00:00', $row->saved_at->format('Y-m-d H:i:s'));
    }

    public static function calendarMonthNameCases(): iterable
    {
        yield 'january' => [2026, 1, 'January'];
        yield 'february_leap' => [2024, 2, 'February'];
        yield 'june' => [2025, 6, 'June'];
        yield 'july' => [2026, 7, 'July'];
        yield 'december' => [2025, 12, 'December'];
    }

    #[DataProvider('calendarMonthNameCases')]
    public function test_month_name_accessor_matches_english_calendar_month(int $year, int $month, string $expected): void
    {
        // Mutation intent: `IncrementInteger` / `DecrementInteger` on `Carbon::createFromDate($this->year, $this->month, 1)` (~34) must keep the stored year/month (day anchored to 1).
        $row = ActiveUsersPerMonth::query()->create([
            'year' => $year,
            'month' => $month,
            'saved_at' => now(),
            'value' => 10,
        ]);

        $this->assertSame($expected, $row->fresh()->month_name);
    }

    public function test_year_month_accessor(): void
    {
        $row = ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 2,
            'saved_at' => now(),
            'value' => 5,
        ]);

        $this->assertSame('2026-02', $row->fresh()->year_month);
    }

    public function test_scopes_for_year_month_and_year_month(): void
    {
        ActiveUsersPerMonth::query()->create([
            'year' => 2025,
            'month' => 1,
            'saved_at' => now(),
            'value' => 1,
        ]);
        ActiveUsersPerMonth::query()->create([
            'year' => 2025,
            'month' => 6,
            'saved_at' => now(),
            'value' => 2,
        ]);
        ActiveUsersPerMonth::query()->create([
            'year' => 2024,
            'month' => 6,
            'saved_at' => now(),
            'value' => 3,
        ]);

        $this->assertSame(2, ActiveUsersPerMonth::query()->forYear(2025)->count());
        $this->assertSame(2, ActiveUsersPerMonth::query()->forMonth(6)->count());
        $this->assertSame(
            1,
            ActiveUsersPerMonth::query()->forYearMonth(2025, 6)->count()
        );
        $this->assertSame(
            2,
            ActiveUsersPerMonth::query()->forYearMonth(2025, 6)->value('value')
        );
    }

    public function test_duplicate_year_month_violates_unique_constraint(): void
    {
        ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 11,
            'saved_at' => now(),
            'value' => 1,
        ]);

        $this->expectException(QueryException::class);
        ActiveUsersPerMonth::query()->create([
            'year' => 2026,
            'month' => 11,
            'saved_at' => now(),
            'value' => 2,
        ]);
    }

    public function test_table_name_is_active_users_per_month(): void
    {
        $this->assertSame('active_users_per_month', (new ActiveUsersPerMonth)->getTable());
    }
}
