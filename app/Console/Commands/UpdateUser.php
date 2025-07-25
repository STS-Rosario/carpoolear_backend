<?php

namespace STS\Console\Commands;

use STS\Models\User;
use STS\Models\Trip;
use STS\Models\Rating;
use STS\Models\Passenger;
use Illuminate\Console\Command;
use STS\Models\References;

class UpdateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:update {original} {new} {--remove}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update trips, ratings and passenger for duplicated users';

    /**
     * Create a new command instance.
     *
     * @return void
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
        \Log::info("COMMAND UpdateUser");
        $originalId = $this->argument('original');
        $newId = $this->argument('new');

        $ratings = Rating::where('user_id_from', '=', $originalId)->get();
        foreach ($ratings as $rating) {
            $rating->user_id_from = $newId;
            $rating->save();
        }

        $ratings = Rating::where('user_id_to', '=', $originalId)->get();
        foreach ($ratings as $rating) {
            $rating->user_id_to = $newId;
            $rating->save();
        }

        $as_passenger = Passenger::where('user_id', '=', $originalId)->get();
        foreach ($as_passenger as $passenger) {
            $passenger->user_id = $newId;
            $passenger->save();
        }

        $trips = Trip::where('user_id', '=', $originalId)->get();
        foreach ($trips as $trip) {
            $trip->user_id = $newId;
            $trip->save();
        }

        
        $referencesFrom = References::where('user_id_from', '=', $originalId)->get();
        foreach ($referencesFrom as $reference) {
            $reference->user_id_from = $newId;
            $reference->save();
        }

        
        $referencesTo = References::where('user_id_to', '=', $originalId)->get();
        foreach ($referencesTo as $reference) {
            $reference->user_id_to = $newId;
            $reference->save();
        }


        if ($this->option('remove') && $this->confirm('Do you wish to continue? This will remove the user from the database [y|N]')) {
            $user = User::find($originalId);
            $user->active = 0;
            $user->save();
            $this->info('User has been removed.');
        }

        $this->info('Trips, references ratings and passenger have been updated.');
    }
}
