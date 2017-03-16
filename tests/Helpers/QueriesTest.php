<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QueriesTest extends TestCase
{
    use DatabaseTransactions;
 
    function test_make_pagination () 
    {
        $users = factory(STS\User::class, 50)->create();

        $query = \STS\User::orderBy('email');
        $answer = make_pagination($query, 1, 20);
        $answerDecode = json_decode(json_encode($answer));
        $this->assertTrue($answerDecode->total == 50 && $answerDecode->last_page == 3);
    }

    function test_make_pagination_no_page_specify_must_return_all_messages ()
    {
        $users = factory(STS\User::class, 53)->create();

        $query = \STS\User::orderBy('email');
        $answer = make_pagination($query, null, null);
        $answerDecode = json_decode(json_encode($answer));
        $this->assertTrue(count($answerDecode) == 53);
    }
}
