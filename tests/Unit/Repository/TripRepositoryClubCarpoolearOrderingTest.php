<?php

namespace Tests\Unit\Repository;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\TripRepository;
use Tests\TestCase;

class TripRepositoryClubCarpoolearOrderingTest extends TestCase
{
    private function repo(): TripRepository
    {
        return $this->app->make(TripRepository::class);
    }

    public function test_search_prioritizes_club_member_trips_within_same_day_and_hour(): void
    {
        $tz = 'America/Argentina/Buenos_Aires';
        $prevTz = date_default_timezone_get();
        Config::set('app.timezone', $tz);
        date_default_timezone_set($tz);
        Carbon::setTestNow(Carbon::parse('2026-01-10 12:00:00', $tz));

        try {
            $admin = User::factory()->create();
            $admin->forceFill(['is_admin' => true])->saveQuietly();

            $clubDriver = User::factory()->create(['monthly_donate' => true]);
            $regularDriver = User::factory()->create(['monthly_donate' => false]);

            $center = Carbon::parse('2026-06-11', $tz)->startOfDay();
            $dateStr = $center->format('Y-m-d');

            $regularEarlier = Trip::factory()->create([
                'user_id' => $regularDriver->id,
                'friendship_type_id' => Trip::PRIVACY_PUBLIC,
                'state' => Trip::STATE_READY,
                'needs_sellado' => 0,
                'weekly_schedule' => 0,
                'trip_date' => $center->copy()->setTime(7, 10, 0),
            ]);
            $clubLater = Trip::factory()->create([
                'user_id' => $clubDriver->id,
                'friendship_type_id' => Trip::PRIVACY_PUBLIC,
                'state' => Trip::STATE_READY,
                'needs_sellado' => 0,
                'weekly_schedule' => 0,
                'trip_date' => $center->copy()->setTime(7, 45, 0),
            ]);

            $page = $this->repo()->search($admin, [
                'date' => $dateStr,
                'page' => 1,
                'page_size' => 50,
            ]);

            $items = collect($page->items())->values();
            $subset = $items->filter(
                fn ($t) => in_array($t->id, [$regularEarlier->id, $clubLater->id], true)
            )->values();

            $this->assertCount(2, $subset);
            $this->assertSame([$clubLater->id, $regularEarlier->id], $subset->pluck('id')->all());
        } finally {
            Carbon::setTestNow();
            date_default_timezone_set($prevTz);
        }
    }
}
