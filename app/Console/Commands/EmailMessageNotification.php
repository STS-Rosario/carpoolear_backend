<?php

namespace STS\Console\Commands;

use Carbon\Carbon;
use STS\Models\Message;
use Illuminate\Console\Command;
use STS\Notifications\NewMessageNotification;

class EmailMessageNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify by email pending messages';

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
        \Log::info("COMMAND EmailMessageNotification");
        // notified once each conversation
        $messages = Message::with(['from', 'users'])->where('already_notified', 0)->get();
        $conversation_notified = [];
        foreach ($messages as $message) {
            // mark as notified
            $message->already_notified = 1; 
            $message->save();

            $conv_key = $message->conversation_id . '_' . $message->user_id;
            // verified is conversation has not been notified yet in this round
            if (!in_array($conv_key, $conversation_notified)) {
                // send notification
                $conversation_notified[] = $conv_key;
                $from = $message->from;
                $message = $message;
                $notification = new NewMessageNotification();
                $notification->setAttribute('from', $from);
                $notification->setAttribute('messages', $message);
                foreach ($message->users as $to) {
                    try {
                        // $notification->notify($to);
                    } catch (\Exception $e) {
                        \Log::info('Error on sending notification');
                        \Log::info($e);
                    }
                }
            }
        }
    }
}
