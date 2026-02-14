<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QueriesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_make_pagination()
    {
        $users = \STS\Models\User::factory()->count(5)->create();

        $query = \STS\Models\User::orderBy('email');
        $answer = make_pagination($query, 1, 2);
        $answerDecode = json_decode(json_encode($answer));
        $this->assertTrue($answerDecode->total == 5 && $answerDecode->last_page == 3);
    }

    public function test_make_pagination_no_page_specify_must_return_all_messages()
    {
        $users = \STS\Models\User::factory()->count(5)->create();

        $query = \STS\Models\User::orderBy('email');
        $answer = make_pagination($query, null, null);
        $answerDecode = json_decode(json_encode($answer));
        $this->assertTrue(count($answerDecode) == 5);
    }
}
