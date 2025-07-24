<?php

namespace STS\Services;

use STS\Models\User;
use STS\Models\Device;
use STS\Notifications\AnnouncementNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnnouncementService
{
    /**
     * Send announcement to all users with advanced options
     */
    public function sendAnnouncement($message, $options = [])
    {
        $defaults = [
            'title' => 'Carpoolear',
            'external_url' => null,
            'batch_size' => 100,
            'delay_between_batches' => 1, // seconds
            'delay_between_users' => 0.1, // seconds
            'active_only' => false,
            'device_activity_days' => 0,
            'max_retries' => 3,
            'rate_limit_per_minute' => 1000,
        ];

        $options = array_merge($defaults, $options);

        // Build user query
        $userQuery = User::where('active', true)
                        ->where('banned', false);

        if ($options['active_only']) {
            $userQuery->where('last_connection', '>=', Carbon::now()->subDays(30));
        }

        $totalUsers = $userQuery->count();
        
        if ($totalUsers === 0) {
            return [
                'success' => false,
                'message' => 'No users found matching the criteria',
                'stats' => ['total' => 0, 'processed' => 0, 'successful' => 0, 'failed' => 0]
            ];
        }

        $stats = [
            'total' => $totalUsers,
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        // Process users in batches
        $userQuery->chunk($options['batch_size'], function ($users) use (
            $message, 
            $options, 
            &$stats
        ) {
            foreach ($users as $user) {
                $stats['processed']++;
                
                $result = $this->sendToUser($user, $message, $options);
                
                if ($result['success']) {
                    $stats['successful']++;
                } elseif ($result['skipped']) {
                    $stats['skipped']++;
                } else {
                    $stats['failed']++;
                }

                // Rate limiting delay
                usleep($options['delay_between_users'] * 1000000);
            }

            // Delay between batches
            if ($options['delay_between_batches'] > 0) {
                sleep($options['delay_between_batches']);
            }
        });

        return [
            'success' => true,
            'message' => 'Announcement completed',
            'stats' => $stats
        ];
    }

    /**
     * Send announcement to a specific user
     */
    public function sendToUser($user, $message, $options = [])
    {
        try {
            // Check if user has devices with notifications enabled
            $devicesQuery = $user->devices()->where('notifications', true);
            
            if ($options['device_activity_days'] > 0) {
                $devicesQuery->where('last_activity', '>=', Carbon::now()->subDays($options['device_activity_days']));
            }
            
            $devices = $devicesQuery->get();
            
            if ($devices->isEmpty()) {
                return [
                    'success' => false,
                    'skipped' => true,
                    'message' => 'No active devices found'
                ];
            }

            // Create and send notification
            $notification = new AnnouncementNotification();
            $notification->setAttribute('message', $message);
            $notification->setAttribute('title', $options['title']);
            $notification->setAttribute('external_url', $options['external_url']);
            $notification->setAttribute('announcement_id', uniqid('ann_'));
            
            $notification->notify($user);
            
            return [
                'success' => true,
                'message' => 'Notification sent successfully',
                'devices_count' => $devices->count()
            ];

        } catch (\Exception $e) {
            Log::error("Announcement failed for user {$user->id}: " . $e->getMessage(), [
                'user_id' => $user->id,
                'message' => $message,
                'exception' => $e
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send announcement to specific users by IDs
     */
    public function sendToUsers($userIds, $message, $options = [])
    {
        // Handle comma-separated string or array
        if (is_string($userIds)) {
            $userIds = array_map('trim', explode(',', $userIds));
        }
        
        // Convert to integers and filter out invalid values
        $userIds = array_filter(array_map('intval', $userIds));
        
        if (empty($userIds)) {
            return [
                'success' => false,
                'message' => 'No valid user IDs provided',
                'stats' => ['total' => 0, 'found' => 0, 'processed' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0]
            ];
        }

        $users = User::whereIn('id', $userIds)
                    ->where('active', true)
                    ->where('banned', false)
                    ->get();

        $stats = [
            'total' => count($userIds),
            'found' => $users->count(),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($users as $user) {
            $stats['processed']++;
            
            $result = $this->sendToUser($user, $message, $options);
            
            if ($result['success']) {
                $stats['successful']++;
            } elseif ($result['skipped']) {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }

        return [
            'success' => true,
            'message' => 'Targeted announcement completed',
            'stats' => $stats
        ];
    }

    /**
     * Get user statistics for announcements
     */
    public function getUserStats()
    {
        $totalUsers = User::where('active', true)->where('banned', false)->count();
        $activeUsers = User::where('active', true)
                          ->where('banned', false)
                          ->where('last_connection', '>=', Carbon::now()->subDays(30))
                          ->count();
        
        $usersWithDevices = User::where('active', true)
                               ->where('banned', false)
                               ->whereHas('devices', function($query) {
                                   $query->where('notifications', true);
                               })
                               ->count();

        $totalDevices = Device::where('notifications', true)->count();
        $activeDevices = Device::where('notifications', true)
                              ->where('last_activity', '>=', Carbon::now()->subDays(30))
                              ->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'users_with_devices' => $usersWithDevices,
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
        ];
    }

    /**
     * Check if we can send announcements (rate limiting)
     */
    public function canSendAnnouncement()
    {
        $key = 'announcement_rate_limit';
        $limit = 10; // Max 10 announcements per hour
        $current = Cache::get($key, 0);
        
        if ($current >= $limit) {
            return false;
        }
        
        Cache::put($key, $current + 1, 3600); // 1 hour
        return true;
    }
} 