<?php

namespace Tests\Unit\Mail;

use STS\Mail\ClubCarpoolearWelcome;
use Tests\TestCase;

class ClubCarpoolearWelcomeMailTest extends TestCase
{
    public function test_constructor_exposes_expected_public_properties(): void
    {
        $mail = new ClubCarpoolearWelcome(
            tripsUrl: 'https://app.example/app/trips',
            heroImageUrl: 'https://api.example/img/carpoolear-grupal-1.jpg',
        );

        $this->assertSame('https://app.example/app/trips', $mail->tripsUrl);
        $this->assertSame('https://api.example/img/carpoolear-grupal-1.jpg', $mail->heroImageUrl);
    }

    public function test_content_uses_welcome_email_views(): void
    {
        $mail = new ClubCarpoolearWelcome(
            tripsUrl: 'https://app.example/app/trips',
            heroImageUrl: 'https://api.example/img/carpoolear-grupal-1.jpg',
        );

        $content = $mail->content();

        $this->assertSame('email.club_carpoolear_welcome', $content->view);
        $this->assertSame('email.club_carpoolear_welcome_text', $content->text);
    }

    public function test_welcome_mailable_renders_html_and_text_in_spanish(): void
    {
        app()->setLocale('arg');

        $mail = new ClubCarpoolearWelcome(
            tripsUrl: 'https://app.example/app/trips',
            heroImageUrl: 'https://api.example/img/carpoolear-grupal-1.jpg',
        );

        $html = $mail->render();
        $this->assertStringContainsString('Club Carpoolear', $html);
        $this->assertStringContainsString('Soporte prioritario:', $html);
        $this->assertStringContainsString('https://app.example/app/trips', $html);
        $this->assertStringContainsString('https://api.example/img/carpoolear-grupal-1.jpg', $html);

        app()->setLocale('en');
        $htmlEn = $mail->render();
        $this->assertStringContainsString('You joined the Carpoolear Club', $htmlEn);
        $this->assertStringContainsString('Priority support:', $htmlEn);
    }
}
