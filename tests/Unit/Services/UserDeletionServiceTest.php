<?php

namespace Tests\Unit\Services;

use STS\Models\User;
use STS\Services\UserDeletionService;
use Tests\TestCase;

class UserDeletionServiceTest extends TestCase
{
    public function test_delete_user_removes_record_and_returns_true(): void
    {
        $user = User::factory()->create();

        $service = new UserDeletionService;
        $result = $service->deleteUser($user);

        $this->assertTrue($result);
        $this->assertNull(User::query()->find($user->id));
    }

    public function test_delete_user_returns_true_when_user_record_does_not_exist(): void
    {
        $user = new User;
        $user->id = 9_999_999;

        $service = new UserDeletionService;
        $result = $service->deleteUser($user);

        $this->assertTrue($result);
        $this->assertNull(User::query()->find($user->id));
    }

    public function test_delete_user_can_be_called_twice_for_same_user_instance(): void
    {
        $user = User::factory()->create();
        $service = new UserDeletionService;

        $first = $service->deleteUser($user);
        $second = $service->deleteUser($user);

        $this->assertTrue($first);
        $this->assertTrue($second);
        $this->assertNull(User::query()->find($user->id));
    }

    public function test_delete_user_does_not_remove_other_users(): void
    {
        $toDelete = User::factory()->create();
        $otherUser = User::factory()->create();

        $service = new UserDeletionService;
        $this->assertTrue($service->deleteUser($toDelete));

        $this->assertNull(User::query()->find($toDelete->id));
        $this->assertNotNull(User::query()->find($otherUser->id));
    }
}
