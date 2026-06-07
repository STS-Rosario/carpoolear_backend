<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use STS\Models\PasswordReset;
use STS\Models\User;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fillable_lists_persisted_columns(): void
    {
        $expected = [
            'email',
            'token',
            'created_at',
        ];

        $this->assertSame($expected, (new PasswordReset)->getFillable());
    }

    public function test_casts_created_at_to_datetime(): void
    {
        $casts = (new PasswordReset)->getCasts();

        $this->assertSame('datetime', $casts['created_at']);
    }

    public function test_created_at_is_cast_when_loaded_from_database(): void
    {
        Carbon::setTestNow('2028-06-01 12:00:00');
        $user = User::factory()->create(['email' => 'reset-model-'.uniqid('', true).'@example.com']);

        PasswordReset::query()->create([
            'email' => $user->email,
            'token' => 'tok-'.uniqid('', true),
            'created_at' => now(),
        ]);

        $reset = PasswordReset::query()->where('email', $user->email)->first();

        $this->assertInstanceOf(CarbonInterface::class, $reset->created_at);
        $this->assertTrue($reset->created_at->equalTo(Carbon::parse('2028-06-01 12:00:00')));
    }
}
