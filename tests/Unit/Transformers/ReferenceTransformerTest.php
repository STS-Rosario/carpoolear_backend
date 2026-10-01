<?php

namespace Tests\Unit\Transformers;

use STS\Models\References;
use STS\Models\User;
use STS\Transformers\ReferenceTransformer;
use Tests\TestCase;

class ReferenceTransformerTest extends TestCase
{
    public function test_transform_includes_reply_fields(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'Great person.',
            'reply_comment' => 'Thanks!',
            'reply_comment_created_at' => '2026-08-30 18:00:00',
        ]);

        $payload = (new ReferenceTransformer)->transform($reference->fresh());

        $this->assertSame($reference->id, $payload['id']);
        $this->assertSame('Great person.', $payload['comment']);
        $this->assertSame('Thanks!', $payload['reply_comment']);
        $this->assertSame('2026-08-30 18:00:00', $payload['reply_comment_created_at']);
    }

    public function test_transform_formats_null_reply_created_at(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $reference = References::query()->create([
            'user_id_from' => $from->id,
            'user_id_to' => $to->id,
            'comment' => 'No reply yet.',
        ]);

        $payload = (new ReferenceTransformer)->transform($reference->fresh());

        $this->assertNull($payload['reply_comment']);
        $this->assertNull($payload['reply_comment_created_at']);
    }
}
