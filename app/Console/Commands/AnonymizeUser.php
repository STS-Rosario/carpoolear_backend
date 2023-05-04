<?php

namespace STS\Console\Commands;

use STS\User;
use Illuminate\Console\Command;

/*
 * Console/Commands/AnonymizeUser.php
 *
 * In order to keep the database integrity, users are not removed from the
 * database. Instead, we remove all the user's personal sensitive data and
 * rename it as an anonymous user.
 *
 * This way, we can keep all the existent references to the deactivated
 * user (e.g. trips, messages, ratings).
 */

class AnonymizeUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:anonymize {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Anonymize personal info and deactivate user';

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
        $userId = $this->argument('id');

        $user = User::find($userId);

        $this->info("User (id=$userId) current data:");
        $this->info($user);

        $user->name = "Usuario anónimo";
        $user->email = null;
        $user->birthday = null;
        $user->gender = null;
        $user->nro_doc = null;
        $user->description = null;
        $user->mobile_phone = null;
        $user->image = null;
        $user->account_number = null;
        $user->account_bank = null;
        $user->account_type = null;

        $user->active = 0;
        $user->save();

        $this->info('User deactivated and personal info has been anonymized.');
    }
}
