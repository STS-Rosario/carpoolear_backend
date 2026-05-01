<?php

namespace Tests\Unit\Services;

use STS\Models\User;
use STS\Services\AnonymizationService;
use Tests\TestCase;

class AnonymizationServiceTest extends TestCase
{
    public function test_anonymize_clears_personal_data_and_deactivates_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'birthday' => '1990-01-01',
            'gender' => 'f',
            'nro_doc' => '30123456',
            'description' => 'Profile bio',
            'mobile_phone' => '+5491122223333',
            'image' => 'profile.jpg',
            'account_number' => '123456789',
            'account_bank' => 'Test Bank',
            'account_type' => 'savings',
            'active' => 1,
        ]);

        $service = new AnonymizationService;
        $result = $service->anonymize($user);

        $this->assertInstanceOf(User::class, $result);
        $fresh = $user->fresh();
        $this->assertSame('Usuario anónimo', $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->birthday);
        $this->assertNull($fresh->gender);
        $this->assertNull($fresh->nro_doc);
        $this->assertNull($fresh->description);
        $this->assertNull($fresh->mobile_phone);
        $this->assertNull($fresh->image);
        $this->assertNull($fresh->account_number);
        $this->assertNull($fresh->account_bank);
        $this->assertNull($fresh->account_type);
        $this->assertSame(0, (int) $fresh->active);
    }

    public function test_anonymize_is_idempotent_for_already_anonymized_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Usuario anónimo',
            'email' => null,
            'birthday' => null,
            'gender' => null,
            'nro_doc' => null,
            'description' => null,
            'mobile_phone' => null,
            'image' => null,
            'account_number' => null,
            'account_bank' => null,
            'account_type' => null,
            'active' => 0,
        ]);

        $service = new AnonymizationService;
        $result = $service->anonymize($user);

        $this->assertInstanceOf(User::class, $result);
        $fresh = $user->fresh();
        $this->assertSame('Usuario anónimo', $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertSame(0, (int) $fresh->active);
    }
}
