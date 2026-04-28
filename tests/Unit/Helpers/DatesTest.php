<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use Tests\TestCase;

class DatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('parse_date')) {
            require_once app_path('Helpers/Dates.php');
        }
    }

    public function test_parse_date_uses_default_format(): void
    {
        $result = parse_date('2026-04-28');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2026-04-28', $result->format('Y-m-d'));
    }

    public function test_parse_date_supports_custom_format(): void
    {
        $result = parse_date('28/04/2026 09:15', 'd/m/Y H:i');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2026-04-28 09:15:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_date_to_string_uses_given_format(): void
    {
        $date = Carbon::create(2026, 4, 28, 9, 30, 5);

        $this->assertSame('2026-04-28', date_to_string($date));
        $this->assertSame('28/04/2026 09:30', date_to_string($date, 'd/m/Y H:i'));
    }

    public function test_parse_boolean_handles_common_true_and_false_values(): void
    {
        $this->assertTrue(parse_boolean('true'));
        $this->assertTrue(parse_boolean('1'));
        $this->assertTrue(parse_boolean('yes'));

        $this->assertFalse(parse_boolean('false'));
        $this->assertFalse(parse_boolean('0'));
        $this->assertFalse(parse_boolean('no'));
        $this->assertFalse(parse_boolean('unexpected-value'));
    }
}
