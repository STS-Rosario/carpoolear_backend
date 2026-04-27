<?php

namespace Tests\Feature\Commands;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    /**
     * Get all scheduled events keyed by command name.
     * Returns an array of Event objects grouped by command signature.
     */
    protected function getScheduledEvents(): array
    {
        $schedule = app(Schedule::class);
        $events = [];

        foreach ($schedule->events() as $event) {
            if (preg_match("/artisan['\"]?\s+(\S+)/", $event->command, $matches)) {
                $events[$matches[1]][] = $event;
            }
        }

        return $events;
    }

    protected function findEvent(string $command, int $index = 0): Event
    {
        $events = $this->getScheduledEvents();
        $this->assertArrayHasKey($command, $events, "Command '{$command}' is not scheduled.");

        return $events[$command][$index];
    }

    // -- Hourly commands --

    public function test_rate_create_is_scheduled_hourly()
    {
        $event = $this->findEvent('rate:create');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    public function test_trip_remainder_is_scheduled_hourly()
    {
        $event = $this->findEvent('trip:remainder');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    public function test_node_buildweights_is_scheduled_hourly()
    {
        $event = $this->findEvent('node:buildweights');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    // -- Every minute / every N minutes --

    public function test_rating_availables_is_scheduled_every_minute()
    {
        $event = $this->findEvent('rating:availables');
        $this->assertEquals('* * * * *', $event->expression);
    }

    public function test_messages_email_is_scheduled_every_ten_minutes()
    {
        $event = $this->findEvent('messages:email');
        $this->assertEquals('*/10 * * * *', $event->expression);
    }

    // -- Daily commands with timezone --

    public function test_trip_request_is_scheduled_twice_daily()
    {
        $events = $this->getScheduledEvents();
        $this->assertArrayHasKey('trip:request', $events);
        $this->assertCount(2, $events['trip:request'], 'trip:request should be scheduled twice daily.');

        // 12:00 Buenos Aires
        $this->assertEquals('0 12 * * *', $events['trip:request'][0]->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $events['trip:request'][0]->timezone);

        // 19:00 Buenos Aires
        $this->assertEquals('0 19 * * *', $events['trip:request'][1]->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $events['trip:request'][1]->timezone);
    }

    public function test_trip_visibilityclean_is_scheduled_daily_at3_am()
    {
        $event = $this->findEvent('trip:visibilityclean');
        $this->assertEquals('0 3 * * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    public function test_cleanup_reset_tokens_is_scheduled_daily_at4_am()
    {
        $event = $this->findEvent('auth:cleanup-reset-tokens');
        $this->assertEquals('0 4 * * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    public function test_support_tickets_autoclose_is_scheduled_daily_at430_am()
    {
        $event = $this->findEvent('support-tickets:autoclose');
        $this->assertEquals('30 4 * * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    // -- Monthly commands --

    public function test_calculate_active_users_is_scheduled_monthly_on_first()
    {
        $event = $this->findEvent('users:calculate-active-per-month');
        $this->assertEquals('0 3 1 * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    // -- Overall schedule integrity --

    public function test_no_unexpected_commands_in_schedule()
    {
        $events = $this->getScheduledEvents();

        $expectedCommands = [
            'rate:create',
            'trip:remainder',
            'rating:availables',
            'trip:request',
            'trip:visibilityclean',
            'node:buildweights',
            'messages:email',
            'users:calculate-active-per-month',
            'auth:cleanup-reset-tokens',
            'support-tickets:autoclose',
        ];

        foreach (array_keys($events) as $command) {
            $this->assertContains($command, $expectedCommands, "Unexpected command '{$command}' found in schedule.");
        }

        foreach ($expectedCommands as $expected) {
            $this->assertArrayHasKey($expected, $events, "Expected command '{$expected}' missing from schedule.");
        }
    }
}
