<?php

namespace STS\Console\Commands;

use STS\Models\User;
use Carbon\Carbon;
use STS\Models\Conversation;
use Illuminate\Console\Command;
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
        \Log::info("COMMAND ConversationCreate");
        $userTo = User::find($this->argument('to'));
        $userFrom = User::find($this->argument('from'));

        //Create new conversation
        $newConversation = $this->conversation->findOrCreatePrivateConversation($userFrom, $userTo);
        if ($newConversation) {
            $this->info('Conversation has been created.');
        } else {
            $this->error('Conversation could not be created, maybe none of the users are admin?');
        }
    }
}
