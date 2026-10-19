<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Jobs\SendClubCarpoolearWelcomeEmail;
use STS\Models\User;
use STS\Support\UserLocale;

class TestClubCarpoolearWelcomeEmail extends Command
{
    protected $signature = 'club:welcome-email-test
        {userId : ID of the user to send the welcome email to}
        {--email= : Override recipient email (defaults to the user email)}';

    protected $description = 'Send the Club Carpoolear welcome email to a user on demand (for testing)';

    public function handle(): int
    {
        $user = User::query()->find($this->argument('userId'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $override = $this->option('email') ?: null;
        $recipient = $override ?? $user->email;
        $locale = UserLocale::resolve($user, 'arg');

        $this->info('Sending Club Carpoolear welcome email...');
        $this->line("User: {$user->id} ({$user->name})");
        $this->line("Recipient: {$recipient}");
        $this->line("Locale: {$locale}");

        try {
            dispatch_sync(new SendClubCarpoolearWelcomeEmail($user, $override, skipClaim: true));
        } catch (\Throwable $e) {
            $this->error("Welcome email could not be sent: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Welcome email sent to {$recipient}.");

        return self::SUCCESS;
    }
}
