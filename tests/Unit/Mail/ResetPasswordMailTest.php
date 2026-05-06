<?php

namespace Tests\Unit\Mail;

use STS\Mail\ResetPassword;
use STS\Models\User;
use Tests\TestCase;

class ResetPasswordMailTest extends TestCase
{
    public function test_constructor_exposes_expected_public_properties(): void
    {
        $user = User::factory()->create();
        $mail = new ResetPassword(
            token: 'token-123',
            user: $user,
            url: 'https://example.com/reset/token-123',
            name_app: 'Carpoolear',
            domain: 'https://example.com'
        );

        $this->assertSame('token-123', $mail->token);
        $this->assertTrue($mail->user->is($user));
        $this->assertSame('https://example.com/reset/token-123', $mail->url);
        $this->assertSame('Carpoolear', $mail->name_app);
        $this->assertSame('https://example.com', $mail->domain);
    }

    public function test_content_uses_reset_password_email_view(): void
    {
        $user = User::factory()->create();
        $mail = new ResetPassword(
            token: 'token-xyz',
            user: $user,
            url: 'https://example.com/reset/token-xyz',
            name_app: 'Carpoolear',
            domain: 'https://example.com'
        );

        $content = $mail->content();

        $this->assertSame('email.reset_password', $content->view);
    }
}
