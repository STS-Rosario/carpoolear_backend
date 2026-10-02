<?php

namespace Tests\Feature\Http;

use STS\Models\User;
use Tests\TestCase;

class ClubCarpoolearMembersApiTest extends TestCase
{
    public function test_lists_public_club_members_ordered_by_join_date_asc(): void
    {
        $hidden = User::factory()->create([
            'monthly_donate' => true,
            'show_club_carpoolear_membership' => false,
            'club_carpoolear_joined_at' => now()->subDays(10),
            'name' => 'Hidden Member',
        ]);
        $older = User::factory()->create([
            'monthly_donate' => true,
            'show_club_carpoolear_membership' => true,
            'club_carpoolear_joined_at' => now()->subDays(30),
            'name' => 'Older Member',
        ]);
        $newer = User::factory()->create([
            'monthly_donate' => true,
            'show_club_carpoolear_membership' => true,
            'club_carpoolear_joined_at' => now()->subDays(5),
            'name' => 'Newer Member',
        ]);
        User::factory()->create([
            'monthly_donate' => false,
            'show_club_carpoolear_membership' => true,
            'club_carpoolear_joined_at' => now()->subDays(1),
            'name' => 'Not Active',
        ]);

        $response = $this->getJson('/api/club-carpoolear/members');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$older->id, $newer->id], $ids);
        $this->assertNotContains($hidden->id, $ids);
    }
}
