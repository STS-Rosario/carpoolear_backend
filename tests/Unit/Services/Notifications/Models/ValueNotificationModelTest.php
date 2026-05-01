<?php

namespace Tests\Unit\Services\Notifications\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;
use Tests\TestCase;

class ValueNotificationModelTest extends TestCase
{
    public function test_fillable_includes_value_text_key_and_notification_id(): void
    {
        $v = new ValueNotification;

        $this->assertSame(
            ['value_text', 'key', 'notification_id'],
            $v->getFillable()
        );
    }

    public function test_value_returns_morph_to_instance(): void
    {
        $user = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $user->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $param = new ValueNotification;
        $param->notification_id = $notification->id;
        $param->key = 'trip';
        $param->value()->associate($trip);
        $param->save();

        $fresh = ValueNotification::query()->find($param->id);

        $this->assertInstanceOf(MorphTo::class, $fresh->value());
    }

    public function test_value_resolves_soft_deleted_trip_when_value_type_is_non_empty(): void
    {
        $user = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $user->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $trip->delete();

        $param = new ValueNotification;
        $param->notification_id = $notification->id;
        $param->key = 'trip';
        $param->value()->associate($trip);
        $param->save();

        $fresh = ValueNotification::query()->find($param->id);

        $this->assertNotNull($fresh->value);
        $this->assertTrue($fresh->value->trashed());
        $this->assertSame($trip->id, $fresh->value->id);
    }
}
