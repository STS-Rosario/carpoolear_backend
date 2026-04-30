<?php

namespace Tests\Unit\Helpers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use STS\Models\User;
use Tests\TestCase;

class QueriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_match_array_keeps_arrays_and_wraps_scalars(): void
    {
        $items = ['a', 'b'];
        $this->assertSame($items, match_array($items));
        $this->assertSame(['single'], match_array('single'));
    }

    public function test_make_pagination_returns_paginated_structure_when_page_size_is_provided(): void
    {
        User::factory()->count(5)->create();

        $query = User::orderBy('email');
        $answer = make_pagination($query, 1, 2);
        $answerDecode = json_decode(json_encode($answer), false, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(5, (int) $answerDecode->total);
        $this->assertSame(3, (int) $answerDecode->last_page);
        $this->assertCount(2, $answerDecode->data);
    }

    public function test_make_pagination_defaults_to_first_page_when_page_number_is_not_provided(): void
    {
        User::factory()->count(3)->create();

        $query = User::orderBy('email');
        $paginated = make_pagination($query, null, 2);
        $decoded = json_decode(json_encode($paginated), false, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, (int) $decoded->current_page);
        $this->assertCount(2, $decoded->data);
    }

    public function test_make_pagination_returns_all_rows_when_page_size_is_null(): void
    {
        User::factory()->count(5)->create();

        $query = User::orderBy('email');
        $answer = make_pagination($query, null, null);
        $answerDecode = json_decode(json_encode($answer), false, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(5, $answerDecode);
    }

    public function test_query_log_helpers_return_latest_and_indexed_query_text_with_bindings(): void
    {
        start_log_query();

        User::where('id', 1)->first();
        User::where('email', 'like', '%@%')->first();

        $latest = get_query();
        $first = get_query(0);

        stop_log_query();

        $this->assertStringContainsString('select', strtolower($latest));
        $this->assertStringContainsString('"%@%"', $latest);
        $this->assertStringContainsString('select', strtolower($first));
        $this->assertStringContainsString('[1]', $first);
    }

    public function test_stop_log_query_disables_logging_for_subsequent_queries(): void
    {
        start_log_query();
        User::where('id', 1)->first();
        stop_log_query();

        DB::flushQueryLog();
        User::where('id', 2)->first();

        $this->assertSame([], DB::getQueryLog());
    }
}
