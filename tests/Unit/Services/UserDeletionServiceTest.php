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
}
