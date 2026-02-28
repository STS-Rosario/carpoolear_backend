<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\Event;

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

    public function testRateCreateIsScheduledHourly()
    {
        $event = $this->findEvent('rate:create');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    public function testTripRemainderIsScheduledHourly()
    {
        $event = $this->findEvent('trip:remainder');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    public function testNodeBuildweightsIsScheduledHourly()
    {
        $event = $this->findEvent('node:buildweights');
        $this->assertEquals('0 * * * *', $event->expression);
    }

    // -- Every minute / every N minutes --

    public function testRatingAvailablesIsScheduledEveryMinute()
    {
        $event = $this->findEvent('rating:availables');
        $this->assertEquals('* * * * *', $event->expression);
    }

    public function testMessagesEmailIsScheduledEveryTenMinutes()
    {
        $event = $this->findEvent('messages:email');
        $this->assertEquals('*/10 * * * *', $event->expression);
    }

    // -- Daily commands with timezone --

    public function testTripRequestIsScheduledTwiceDaily()
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

    public function testTripVisibilitycleanIsScheduledDailyAt3AM()
    {
        $event = $this->findEvent('trip:visibilityclean');
        $this->assertEquals('0 3 * * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    public function testCleanupResetTokensIsScheduledDailyAt4AM()
    {
        $event = $this->findEvent('auth:cleanup-reset-tokens');
        $this->assertEquals('0 4 * * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    // -- Monthly commands --

    public function testCalculateActiveUsersIsScheduledMonthlyOnFirst()
    {
        $event = $this->findEvent('users:calculate-active-per-month');
        $this->assertEquals('0 3 1 * *', $event->expression);
        $this->assertEquals('America/Argentina/Buenos_Aires', $event->timezone);
    }

    // -- Overall schedule integrity --

    public function testNoUnexpectedCommandsInSchedule()
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
        ];

        foreach (array_keys($events) as $command) {
            $this->assertContains($command, $expectedCommands, "Unexpected command '{$command}' found in schedule.");
        }

        foreach ($expectedCommands as $expected) {
            $this->assertArrayHasKey($expected, $events, "Expected command '{$expected}' missing from schedule.");
        }
    }
}
