<?php

namespace Tests\Unit\Models;

use STS\Models\References;
use STS\Models\User;
use Tests\TestCase;

class ReferencesTest extends TestCase
{
    public function test_from_and_to_relationships_resolve_users(): void
    {
        $author = User::factory()->create();
        $subject = User::factory()->create();

        $ref = References::query()->create([
            'user_id_from' => $author->id,
            'user_id_to' => $subject->id,
            'comment' => 'Reliable carpool partner.',
        ]);

        $ref = $ref->fresh();
        $this->assertTrue($ref->from()->first()->is($author));
        $this->assertTrue($ref->to()->first()->is($subject));
    }

    public function test_persists_comment(): void
    {
        $author = User::factory()->create();
        $subject = User::factory()->create();
        $text = 'Long comment '.str_repeat('x', 100);

        $ref = References::query()->create([
            'user_id_from' => $author->id,
            'user_id_to' => $subject->id,
            'comment' => $text,
        ]);

        $this->assertSame($text, $ref->fresh()->comment);
    }

    public function test_appends_from_in_to_array(): void
    {
        $author = User::factory()->create();
        $subject = User::factory()->create();

        $ref = References::query()->create([
            'user_id_from' => $author->id,
            'user_id_to' => $subject->id,
            'comment' => 'Append check.',
        ]);

        $array = $ref->fresh()->toArray();
        $this->assertArrayHasKey('from', $array);
        $this->assertIsArray($array['from']);
        $this->assertSame($author->id, $array['from']['id']);
    }

    public function test_table_name_is_users_references(): void
    {
        $this->assertSame('users_references', (new References)->getTable());
    }
}
