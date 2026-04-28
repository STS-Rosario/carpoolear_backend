<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use STS\Http\Middleware\EncryptCookies;
use Tests\TestCase;

class EncryptCookiesTest extends TestCase
{
    public function test_default_except_list_is_empty_so_cookies_are_not_disabled(): void
    {
        $middleware = new EncryptCookies($this->app->make('encrypter'));

        $this->assertFalse($middleware->isDisabled('laravel_session'));
        $this->assertFalse($middleware->isDisabled('remember_web'));
    }

    public function test_disable_for_marks_cookie_name_as_disabled(): void
    {
        $middleware = new EncryptCookies($this->app->make('encrypter'));
        $middleware->disableFor('locale');

        $this->assertTrue($middleware->isDisabled('locale'));
        $this->assertFalse($middleware->isDisabled('other_cookie'));
    }

    public function test_handle_decrypts_request_and_encrypts_response_pipeline(): void
    {
        $middleware = new EncryptCookies($this->app->make('encrypter'));
        $request = Request::create('/', 'GET');

        $response = $middleware->handle($request, fn () => response('through', 202));

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('through', $response->getContent());
    }

    public function test_disable_for_can_register_multiple_cookies(): void
    {
        $middleware = new EncryptCookies($this->app->make('encrypter'));
        $middleware->disableFor('locale');
        $middleware->disableFor('analytics_optout');

        $this->assertTrue($middleware->isDisabled('locale'));
        $this->assertTrue($middleware->isDisabled('analytics_optout'));
        $this->assertFalse($middleware->isDisabled('session_id'));
    }

    public function test_disable_for_is_idempotent_for_same_cookie(): void
    {
        $middleware = new EncryptCookies($this->app->make('encrypter'));
        $middleware->disableFor('locale');
        $middleware->disableFor('locale');

        $this->assertTrue($middleware->isDisabled('locale'));

        $response = $middleware->handle(Request::create('/', 'GET'), fn () => response('ok', 200));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
