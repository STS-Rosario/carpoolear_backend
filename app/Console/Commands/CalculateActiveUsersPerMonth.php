<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use STS\Models\ActiveUsersPerMonth;
use STS\Models\User;

class CalculateActiveUsersPerMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:calculate-active-per-month 
                            {--month= : Specific month to calculate (format: YYYY-MM, e.g., 2024-01). Cannot be current or future month}
                            {--dry-run : Show what would be calculated without saving to database}
                            {--force : Force recalculation even if data already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store the number of active users for a specific month (cannot calculate for current or future months)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting active users calculation...');

        try {
            $targetMonth = $this->getTargetMonth();
        } catch (\InvalidArgumentException) {
            return 1;
        }
        $this->info("Calculating active users for: {$targetMonth->format('F Y')}");

        // Check if data already exists for this month
        if (! $this->option('force') && $this->dataExistsForMonth($targetMonth)) {
            $this->warn("Data already exists for {$targetMonth->format('F Y')}. Use --force to recalculate.");

            return 0;
        }

        // Calculate active users
        $activeUsersCount = $this->calculateActiveUsers($targetMonth);

        $this->info("Found {$activeUsersCount} active users for {$targetMonth->format('F Y')}");

        if ($this->option('dry-run')) {
            $this->info('DRY RUN: Would save the following data:');
            $this->line("  Year: {$targetMonth->year}");
            $this->line("  Month: {$targetMonth->month}");
            $this->line("  Value: {$activeUsersCount}");

            return 0;
        }

        // Save to database
        $this->saveActiveUsersData($targetMonth, $activeUsersCount);

        $this->info('Active users calculation completed successfully!');

        return 0;
    }

    /**
     * Get the target month for calculation
     */
    protected function getTargetMonth(): Carbon
    {
        if ($monthOption = $this->option('month')) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $monthOption);
            } catch (\Throwable) {
                $this->error('Invalid month format. Use YYYY-MM (e.g., 2024-01)');
                throw new \InvalidArgumentException('Invalid month format');
            }

            $this->validateTargetMonth($targetMonth);

            return $targetMonth;
        }

        $targetMonth = Carbon::now()->subMonth()->startOfMonth();
        $this->validateTargetMonth($targetMonth);

        return $targetMonth;
    }

    /**
     * Validate that the target month is not current or future
     */
    protected function validateTargetMonth(Carbon $targetMonth): void
    {
        $currentMonth = Carbon::now()->startOfMonth();

        if ($targetMonth->greaterThanOrEqualTo($currentMonth)) {
            $this->error("Cannot calculate active users for current month ({$targetMonth->format('F Y')}) or future months.");
            $this->line('Current month data is incomplete and future months will always be 0.');
            $this->line('Please specify a past month or use the default (previous month).');
            throw new \InvalidArgumentException('Target month must be strictly before the current month');
        }
    }

    /**
     * Check if data already exists for the given month
     */
    protected function dataExistsForMonth(Carbon $month): bool
    {
        return ActiveUsersPerMonth::forYearMonth($month->year, $month->month)->exists();
    }

    /**
     * Calculate active users for the given month
     */
    protected function calculateActiveUsers(Carbon $month): int
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        return User::where('active', true)
            ->where('banned', false)
            ->whereNotNull('last_connection')
            ->where('last_connection', '>=', $startOfMonth)
            ->where('last_connection', '<=', $endOfMonth)
            ->count();
    }

    /**
     * Save the active users data to the database
     */
    protected function saveActiveUsersData(Carbon $month, int $count): void
    {
        try {
            // Delete existing record if force option is used
            if ($this->option('force')) {
                ActiveUsersPerMonth::forYearMonth($month->year, $month->month)->delete();
            }

            ActiveUsersPerMonth::create([
                'year' => $month->year,
                'month' => $month->month,
                'saved_at' => Carbon::now(),
                'value' => $count,
            ]);

            $this->info("Data saved successfully for {$month->format('F Y')}");

            Log::info("Active users calculated for {$month->format('F Y')}: {$count} users");
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle unique constraint violation
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'unique_year_month') !== false) {
                $this->warn("Data already exists for {$month->format('F Y')}. This is likely a duplicate run.");
                Log::warning("Duplicate active users calculation attempted for {$month->format('F Y')}");

                return;
            }

            $this->error('Database error: '.$e->getMessage());
            Log::error('Database error in active users calculation: '.$e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->error('Failed to save data: '.$e->getMessage());
            Log::error('Failed to save active users data: '.$e->getMessage());
            throw $e;
        }
    }
}
