<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Mail;
use STS\Console\Commands\TestClubCarpoolearWelcomeEmail;
use STS\Mail\ClubCarpoolearWelcome;
use STS\Models\User;
use Tests\TestCase;

class TestClubCarpoolearWelcomeEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'carpoolear.log_emails' => false,
            'carpoolear.club_welcome_mailer' => null,
        ]);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = app(TestClubCarpoolearWelcomeEmail::class);

        $this->assertSame('club:welcome-email-test', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('userId'));
        $this->assertTrue($command->getDefinition()->hasOption('email'));
    }

    public function test_command_sends_welcome_email_to_user(): void
    {
        $user = User::factory()->create();

        $this->artisan('club:welcome-email-test', ['userId' => $user->id])
            ->expectsOutputToContain('Welcome email sent to '.$user->email.'.')
            ->assertExitCode(0);

        Mail::assertSent(ClubCarpoolearWelcome::class, function (ClubCarpoolearWelcome $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_sends_welcome_email_to_override_address(): void
    {
        $user = User::factory()->create();

        $this->artisan('club:welcome-email-test', [
            'userId' => $user->id,
            '--email' => 'other@example.com',
        ])
            ->expectsOutputToContain('Welcome email sent to other@example.com.')
            ->assertExitCode(0);

        Mail::assertSent(ClubCarpoolearWelcome::class, function (ClubCarpoolearWelcome $mail) {
            return $mail->hasTo('other@example.com');
        });

        Mail::assertNotSent(ClubCarpoolearWelcome::class, function (ClubCarpoolearWelcome $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_fails_when_user_does_not_exist(): void
    {
        $this->artisan('club:welcome-email-test', ['userId' => 999999])
            ->expectsOutput('User not found.')
            ->assertExitCode(1);

        Mail::assertNothingSent();
    }

    public function test_command_does_not_mark_welcome_email_as_sent(): void
    {
        $user = User::factory()->create();

        $this->artisan('club:welcome-email-test', ['userId' => $user->id])->assertExitCode(0);

        $this->assertNull($user->fresh()->club_carpoolear_welcome_email_sent_at);
    }
}
