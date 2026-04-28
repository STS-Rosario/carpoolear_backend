<?php

namespace Tests\Unit\Helpers;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MailsTest extends TestCase
{
    public function test_ssmtp_send_mail_logs_start_message(): void
    {
        if (! function_exists('ssmtp_send_mail')) {
            require_once app_path('Helpers/Mails.php');
        }

        Log::spy();

        ssmtp_send_mail('Subject', 'test@example.com', 'Body');

        Log::shouldHaveReceived('info')->once()->with('ssmtp_send_mail: START');
    }
}
