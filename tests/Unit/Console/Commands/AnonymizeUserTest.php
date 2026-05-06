<?php

namespace Tests\Unit\Console\Commands;

use STS\Console\Commands\AnonymizeUser;
use STS\Models\User;
use Tests\TestCase;

class AnonymizeUserTest extends TestCase
{
    public function test_handle_anonymizes_user_and_deactivates_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'description' => 'Some profile',
            'mobile_phone' => '1234567',
            'image' => 'avatar.jpg',
            'active' => 1,
        ]);

        $this->artisan('user:anonymize', ['id' => $user->id])
            ->expectsOutput("User (id={$user->id}) current data:")
            ->expectsOutputToContain('Original Name')
            ->expectsOutput('User deactivated and personal info has been anonymized.')
            ->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertSame('Usuario anónimo', $fresh->name);
        $this->assertNull($fresh->email);
        $this->assertNull($fresh->description);
        $this->assertNull($fresh->mobile_phone);
        $this->assertNull($fresh->image);
        $this->assertSame(0, (int) $fresh->active);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new AnonymizeUser;

        $this->assertSame('user:anonymize', $command->getName());
        $this->assertStringContainsString('Anonymize personal info and deactivate user', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('id'));
    }
}
