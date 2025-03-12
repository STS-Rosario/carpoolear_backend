<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Console\Command;
use STS\Events\Trip\Alert\RequestNotAnswer as  RequestNotAnswerEvent;

class RequestNotAnswer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:requestnotanswer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify not answer request';

    protected $tripLogic;

    protected $tripRepo;

    /**
     * Create a new command instance.
     *
     * @returnactiveRatings void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \Log::info("COMMAND RequestNotAnswer");
        $now = Carbon::now();

        $passengers = Passenger::whereIn('request_state', [Passenger::STATE_PENDING]);
        $passengers->with(['user', 'trip']);
        $passengers->whereRaw('DATEDIFF(?, DATE(created_at)) = 3', [$now->toDateString()]);
        $passengers = $passengers->get();

        foreach ($passengers as $p) {
            event(new RequestNotAnswerEvent($p->trip, $p->user));
        }
    }
}
