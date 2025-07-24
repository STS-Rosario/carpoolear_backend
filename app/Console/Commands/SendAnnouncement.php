<?php

namespace STS\Console\Commands;

use STS\Models\User;
use STS\Models\Device;
use Carbon\Carbon;
use Illuminate\Console\Command;
use STS\Notifications\AnnouncementNotification;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\AnnouncementService;

class SendAnnouncement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:send 
                            {message : The announcement message to send}
                            {--title=Carpoolear : The title of the announcement}
                            {--url= : External URL to include in the notification}
                            {--batch-size=100 : Number of users to process in each batch}
                            {--dry-run : Show what would be sent without actually sending}
                            {--active-only : Only send to users with recent activity (last 30 days)}
                            {--device-activity-days=0 : Only send to devices with activity within X days (0 = all devices)}
                            {--user-ids= : Comma-separated list of specific user IDs to target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a push notification announcement to all users';

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
        $message = $this->argument('message');
        $title = $this->option('title');
        $externalUrl = $this->option('url');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $activeOnly = $this->option('active-only');
        $deviceActivityDays = (int) $this->option('device-activity-days');
        $userIds = $this->option('user-ids');

        $this->info("=== Carpoolear Announcement Tool ===");
        $this->info("Message: {$message}");
        $this->info("Title: {$title}");
        if ($externalUrl) {
            $this->info("External URL: {$externalUrl}");
        }
        $this->info("Batch Size: {$batchSize}");
        $this->info("Dry Run: " . ($dryRun ? 'Yes' : 'No'));
        $this->info("Active Users Only: " . ($activeOnly ? 'Yes' : 'No'));
        $this->info("Device Activity Days: " . ($deviceActivityDays > 0 ? $deviceActivityDays : 'All devices'));
        if ($userIds) {
            $this->info("Target User IDs: {$userIds}");
        }

        // Show user statistics
        $stats = $this->announcementService->getUserStats();
        $this->info("\n=== User Statistics ===");
        $this->info("Total users: {$stats['total_users']}");
        $this->info("Active users (30 days): {$stats['active_users']}");
        $this->info("Users with devices: {$stats['users_with_devices']}");
        $this->info("Total devices: {$stats['total_devices']}");
        $this->info("Active devices (30 days): {$stats['active_devices']}");

        if (!$this->confirm('Do you want to proceed with sending this announcement?')) {
            $this->info('Announcement cancelled.');
            return;
        }

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No notifications will be sent");
            if ($userIds) {
                $this->info("Would send to specified user IDs: {$userIds}");
            } else {
                $this->info("Would send to approximately {$stats['users_with_devices']} users");
            }
            return;
        }

        // Check rate limiting
        if (!$this->announcementService->canSendAnnouncement()) {
            $this->error('Rate limit exceeded. Please wait before sending another announcement.');
            return;
        }

        // Prepare options for the service
        $options = [
            'title' => $title,
            'external_url' => $externalUrl,
            'batch_size' => $batchSize,
            'active_only' => $activeOnly,
            'device_activity_days' => $deviceActivityDays,
        ];

        $this->info("\nSending announcement...");
        
        // Send the announcement
        if ($userIds) {
            $result = $this->announcementService->sendToUsers($userIds, $message, $options);
        } else {
            $result = $this->announcementService->sendAnnouncement($message, $options);
        }

        // Display results
        $this->info("\n=== Announcement Results ===");
        if ($result['success']) {
            $stats = $result['stats'];
            $this->info("Total users: {$stats['total']}");
            $this->info("Processed: {$stats['processed']}");
            $this->info("Successful: {$stats['successful']}");
            $this->info("Failed: {$stats['failed']}");
            $this->info("Skipped: {$stats['skipped']}");
            $this->info("\n✓ Announcement completed successfully!");
        } else {
            $this->error("✗ Announcement failed: " . $result['message']);
        }
    }
} 