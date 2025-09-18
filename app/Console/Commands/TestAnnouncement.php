<?php

namespace STS\Console\Commands;

use STS\Models\User;
use STS\Models\Device;
use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Services\AnnouncementService;

class TestAnnouncement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:test {--user-id= : Test with specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the announcement system with a single user';

    protected $announcementService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(AnnouncementService $announcementService)
    {
        parent::__construct();
        $this->announcementService = $announcementService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $userId = $this->option('user-id');

        $this->info("=== Testing Announcement System ===");

        // Get user statistics
        $stats = $this->announcementService->getUserStats();
        $this->info("System Statistics:");
        $this->info("- Total users: {$stats['total_users']}");
        $this->info("- Active users: {$stats['active_users']}");
        $this->info("- Users with devices: {$stats['users_with_devices']}");
        $this->info("- Total devices: {$stats['total_devices']}");
        $this->info("- Active devices: {$stats['active_devices']}");

        // Test with specific user or find a test user
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return;
            }
        } else {
            // Find a user with devices for testing
            $user = User::where('active', true)
                       ->where('banned', false)
                       ->whereHas('devices', function($query) {
                           $query->where('notifications', true);
                       })
                       ->first();

            if (!$user) {
                $this->error("No suitable test user found with active devices.");
                return;
            }
        }

        $this->info("\nTesting with user: {$user->name} (ID: {$user->id})");

        // Check user's devices
        $devices = $user->devices()->where('notifications', true)->get();
        $this->info("User has {$devices->count()} devices with notifications enabled:");

        foreach ($devices as $device) {
            $this->line("- Device ID: {$device->device_id}");
            $this->line("  Type: {$device->device_type}");
            $this->line("  Last Activity: {$device->last_activity}");
            $this->line("  Notifications: " . ($device->notifications ? 'Enabled' : 'Disabled'));
        }

        if ($devices->isEmpty()) {
            $this->warn("User has no devices with notifications enabled.");
            return;
        }

        // Test notification sending
        $this->info("\nTesting notification sending...");
        
        $testMessage = "Test announcement - " . Carbon::now()->format('Y-m-d H:i:s');
        $testOptions = [
            'title' => 'Test Announcement',
            'external_url' => 'https://carpoolear.com.ar',
        ];

        $result = $this->announcementService->sendToUser($user, $testMessage, $testOptions);

        if ($result['success']) {
            $this->info("✓ Test notification sent successfully!");
            $this->info("Message: {$testMessage}");
            $this->info("Devices: {$result['devices_count']}");
        } else {
            $this->error("✗ Test notification failed: " . $result['message']);
        }

        $this->info("\nTest completed!");
    }
} 