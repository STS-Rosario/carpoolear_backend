<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DatesHelperTest extends TestCase
{
    public function test_parse_date_returns_carbon_for_default_format(): void
    {
        $parsed = parse_date('2030-11-20');

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame('2030-11-20', $parsed->format('Y-m-d'));
    }

    public function test_parse_date_accepts_custom_format(): void
    {
        $parsed = parse_date('20/11/2030', 'd/m/Y');

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame('2030-11-20', $parsed->format('Y-m-d'));
    }

    public function test_date_to_string_formats_carbon_with_defaults(): void
    {
        $date = Carbon::parse('2031-01-05 14:30:00');

        $this->assertSame('2031-01-05', date_to_string($date));
        $this->assertSame('05/01/2031', date_to_string($date, 'd/m/Y'));
    }

    public function test_parse_boolean_interprets_common_truthy_and_falsy_inputs(): void
    {
        $this->assertTrue(parse_boolean(true));
        $this->assertFalse(parse_boolean(false));
        $this->assertTrue(parse_boolean('true'));
        $this->assertFalse(parse_boolean('false'));
        $this->assertTrue(parse_boolean('1'));
        $this->assertFalse(parse_boolean('0'));
    }
}
