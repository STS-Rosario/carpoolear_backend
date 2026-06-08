<?php

namespace Tests\Unit\Helpers;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MailsTest extends TestCase
{
    public function test_ssmtp_send_mail_runs_without_logging(): void
    {
        if (! function_exists('ssmtp_send_mail')) {
            require_once app_path('Helpers/Mails.php');
        }

        Log::shouldReceive('info')->never();

        ssmtp_send_mail('Subject', 'test@example.com', 'Body');

        $this->assertTrue(true);
    }
}
