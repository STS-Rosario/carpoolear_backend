<?php

namespace Tests\Unit\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use STS\Models\User;
use STS\Services\UserMigrationFieldMerger;
use Tests\TestCase;

class UserMigrationFieldMergerTest extends TestCase
{
    public function test_apply_merges_selected_fields_from_removed_and_kept_users(): void
    {
        $oldCreatedAt = Carbon::parse('2018-03-15 10:00:00');
        $newCreatedAt = Carbon::parse('2024-06-20 14:30:00');

        $removed = User::factory()->create([
            'email' => 'old@example.test',
            'password' => Hash::make('old-password'),
            'nro_doc' => '11111111',
            'mobile_phone' => '+5491111111111',
            'created_at' => $oldCreatedAt,
            'updated_at' => $oldCreatedAt,
        ]);
        $kept = User::factory()->create([
            'email' => 'new@example.test',
            'password' => Hash::make('new-password'),
            'nro_doc' => '22222222',
            'mobile_phone' => '+5492222222222',
            'created_at' => $newCreatedAt,
            'updated_at' => $newCreatedAt,
        ]);

        $merger = new UserMigrationFieldMerger;
        $merger->apply($kept, $removed, [
            'email' => 'removed',
            'password' => 'kept',
            'nro_doc' => 'removed',
            'mobile_phone' => 'kept',
            'created_at' => 'removed',
        ]);

        $kept->refresh();

        $this->assertSame('old@example.test', $kept->email);
        $this->assertTrue(Hash::check('new-password', $kept->password));
        $this->assertSame('11111111', $kept->nro_doc);
        $this->assertSame('+5492222222222', $kept->mobile_phone);
        $this->assertSame(
            $oldCreatedAt->toDateTimeString(),
            $kept->created_at->toDateTimeString()
        );
    }
}
