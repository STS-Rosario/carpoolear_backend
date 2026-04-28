<?php

namespace Tests\Unit\Console\Commands;

use STS\Console\Commands\FacebookImage;
use STS\Models\User;
use STS\Repository\FileRepository;
use Tests\TestCase;

class FacebookImageTest extends TestCase
{
    public function test_handle_outputs_zero_when_no_users_match_trip_and_account_filters(): void
    {
        User::factory()->create();

        $this->artisan('user:facebook')
            ->expectsOutput('0')
            ->assertExitCode(0);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new FacebookImage(new FileRepository);

        $this->assertSame('user:facebook', $command->getName());
        $this->assertStringContainsString('Download profile images facebook from users', $command->getDescription());
    }
}
