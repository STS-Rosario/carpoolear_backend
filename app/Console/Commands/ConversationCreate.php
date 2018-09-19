<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Entities\Conversation;
use Carbon\Carbon;
use STS\User;
use STS\Services\Logic\ConversationsManager as ConversationManager;

class ConversationCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected $signature = 'conversation:create {from} {to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initiate a conversarion between users';
    protected $conversation;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ConversationManager $conversation)
    {
        parent::__construct();
        $this->conversation = $conversation;   

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$userTo = User::find($this->argument('to'));
        $userFrom = User::find($this->argument('from'));

        //Create new conversation
        $conversation = $this->conversation->findOrCreatePrivateConversation($userFrom, $userTo);
		if ($conversation) {
			$message = "Conversation has been created.";
		} else {
			$message = "Conversation could not be created, maybe none of the users are admin?";
		}
		
		$this->error($message);

    }
}
