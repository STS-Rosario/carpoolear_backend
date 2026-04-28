<?php

namespace Tests\Unit\Helpers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\User;
use Tests\TestCase;

class QueriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_make_pagination()
    {
        User::factory()->count(5)->create();

        $query = User::orderBy('email');
        $answer = make_pagination($query, 1, 2);
        $answerDecode = json_decode(json_encode($answer), false, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(5, (int) $answerDecode->total);
        $this->assertSame(3, (int) $answerDecode->last_page);
        $this->assertCount(2, $answerDecode->data);
    }

    public function test_make_pagination_no_page_specify_must_return_all_messages()
    {
        User::factory()->count(5)->create();

        $query = User::orderBy('email');
        $answer = make_pagination($query, null, null);
        $answerDecode = json_decode(json_encode($answer), false, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(5, $answerDecode);
    }
}
