<?php

namespace Tests\Unit\Services;

use STS\Models\User;
use STS\Services\Registrar;
use Tests\TestCase;

class RegistrarTest extends TestCase
{
    public function test_validator_requires_name_email_password_confirmation_rules(): void
    {
        $registrar = new Registrar;

        $missingName = $registrar->validator([
            'email' => 'a@b.com',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);
        $this->assertTrue($missingName->errors()->has('name'));

        $missingEmail = $registrar->validator([
            'name' => 'Test User',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);
        $this->assertTrue($missingEmail->errors()->has('email'));

        $missingPassword = $registrar->validator([
            'name' => 'Test User',
            'email' => 'only@email.com',
        ]);
        $this->assertTrue($missingPassword->errors()->has('password'));
    }

    public function test_validator_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $registrar = new Registrar;

        $validator = $registrar->validator([
            'name' => 'Other',
            'email' => 'taken@example.com',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);

        $this->assertTrue($validator->errors()->has('email'));
    }

    public function test_create_persists_user_with_hashed_password(): void
    {
        $registrar = new Registrar;
        $user = $registrar->create([
            'name' => 'New Member',
            'email' => 'newmember@example.com',
            'password' => 'plain-pass',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('New Member', $user->name);
        $this->assertSame('newmember@example.com', $user->email);
        $this->assertNotSame('plain-pass', $user->password);
        $this->assertTrue(\Hash::check('plain-pass', $user->password));
    }
}
