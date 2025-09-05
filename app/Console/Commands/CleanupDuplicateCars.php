<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\Car;
use STS\Models\User;

class CleanupDuplicateCars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cars:cleanup-duplicates {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate car entries, keeping the newest patente for each user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🚗 Starting cleanup of duplicate car entries...');

        // Find users with multiple cars
        $usersWithMultipleCars = User::withCount('cars')
            ->having('cars_count', '>', 1)
            ->with('cars')
            ->get();

        if ($usersWithMultipleCars->isEmpty()) {
            $this->info('✅ No users with multiple cars found. Database is clean!');
            return 0;
        }

        $this->info("Found {$usersWithMultipleCars->count()} users with multiple cars:");

        $totalDeleted = 0;
        $totalKept = 0;

        foreach ($usersWithMultipleCars as $user) {
            $this->line("👤 User: {$user->name} (ID: {$user->id}) - {$user->cars_count} cars");
            
            // Sort cars by created_at (newest first)
            $cars = $user->cars->sortByDesc('created_at');
            $newestCar = $cars->first();
            $carsToDelete = $cars->skip(1);

            $this->line("   ✅ Keeping: Car ID {$newestCar->id} - Patente: {$newestCar->patente} (Created: {$newestCar->created_at})");
            $totalKept++;

            foreach ($carsToDelete as $car) {
                $this->line("   ❌ Deleting: Car ID {$car->id} - Patente: {$car->patente} (Created: {$car->created_at})");
                
                if (!$isDryRun) {
                    // Check if car is used in any trips
                    $tripCount = $car->trips()->count();
                    if ($tripCount > 0) {
                        $this->warn("   ⚠️  Car ID {$car->id} is used in {$tripCount} trips. Updating trips to use newest car...");
                        
                        // Update trips to use the newest car
                        $car->trips()->update(['car_id' => $newestCar->id]);
                    }
                    
                    $car->delete();
                }
                $totalDeleted++;
            }
            $this->line('');
        }

        if ($isDryRun) {
            $this->info("🔍 DRY RUN SUMMARY:");
            $this->info("   - Would keep: {$totalKept} cars");
            $this->info("   - Would delete: {$totalDeleted} cars");
            $this->info("   - Total users affected: {$usersWithMultipleCars->count()}");
        } else {
            $this->info("✅ CLEANUP COMPLETED:");
            $this->info("   - Kept: {$totalKept} cars");
            $this->info("   - Deleted: {$totalDeleted} cars");
            $this->info("   - Total users affected: {$usersWithMultipleCars->count()}");
        }

        return 0;
    }
}
