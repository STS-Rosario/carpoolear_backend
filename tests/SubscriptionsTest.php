<?php

use STS\User;
use STS\Entities\Trip;
use STS\Entities\Subscription;
use STS\Transformers\RatingTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SubscriptionsTest extends TestCase
{
    use DatabaseTransactions;

    protected $subscriptionsManager;
    protected $subscriptionsRepository;

    public function setUp()
    {
        parent::setUp();
        start_log_query();
        // $this->subscriptionsManager = App::make('\STS\Contracts\Logic\Subscription');
        $this->subscriptionsRepository = App::make('\STS\Contracts\Repository\Subscription');
    }
}
