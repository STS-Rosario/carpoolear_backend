<?php

namespace Tests\Unit\Services\Notifications\Models;

use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Notifications\Models\DatabaseNotification;
use STS\Services\Notifications\Models\ValueNotification;
use Tests\TestCase;

class DatabaseNotificationModelTest extends TestCase
{
    public function test_fillable_includes_user_id_type_and_read_at(): void
    {
        $n = new DatabaseNotification;

        $this->assertSame(
            ['user_id', 'type', 'read_at'],
            $n->getFillable()
        );
    }

    public function test_user_relation_is_belongs_to_user(): void
    {
        $owner = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $owner->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $rel = $notification->user();
        $this->assertSame('user_id', $rel->getForeignKeyName());
        $this->assertInstanceOf(User::class, $notification->user);
        $this->assertSame($owner->id, $notification->user->id);
    }

    public function test_attributes_returns_cached_array_without_rebuilding(): void
    {
        $user = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $user->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $tripValue = new ValueNotification;
        $tripValue->key = 'trip';
        $tripValue->value()->associate($trip);
        $notification->plain_values()->save($tripValue);

        $ref = new \ReflectionProperty(DatabaseNotification::class, '_attributes');
        $ref->setAccessible(true);
        $ref->setValue($notification, ['cached' => true]);

        $attrs = $notification->attributes();

        $this->assertTrue($attrs['cached']);
        $this->assertArrayNotHasKey('trip', $attrs);
    }

    public function test_attributes_maps_plain_row_with_empty_value_type_to_value_text(): void
    {
        $user = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $user->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $plain = new ValueNotification;
        $plain->notification_id = $notification->id;
        $plain->key = 'note';
        $plain->value_type = '';
        $plain->value_id = 0;
        $plain->value_text = 'stored text';
        $plain->save();

        $fresh = DatabaseNotification::query()->with('plain_values')->find($notification->id);
        $this->assertNotNull($fresh);

        $attrs = $fresh->attributes();

        $this->assertSame('stored text', $attrs['note']);
    }

    public function test_attributes_rewrites_legacy_value_type_strings_before_resolving_models(): void
    {
        $owner = User::factory()->create();
        $notification = new DatabaseNotification;
        $notification->user_id = $owner->id;
        $notification->type = 'STS\\Notifications\\DummyNotification';
        $notification->save();

        $actor = User::factory()->create();

        $param = new ValueNotification;
        $param->notification_id = $notification->id;
        $param->key = 'actor';
        $param->value_id = $actor->id;
        $param->value_type = 'STS\\User';
        $param->save();

        $fresh = DatabaseNotification::query()->with('plain_values')->find($notification->id);
        $plain = $fresh->plain_values->first();
        $this->assertSame('STS\\User', $plain->getRawOriginal('value_type'));

        $attrs = $fresh->attributes();

        $this->assertSame($actor->id, $attrs['actor']->id);
        $this->assertInstanceOf(User::class, $attrs['actor']);
        $this->assertSame(User::class, $plain->value_type);
    }
}
