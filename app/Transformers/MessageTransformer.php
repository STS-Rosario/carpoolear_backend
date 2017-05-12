<?php

namespace STS\Transformers;

use STS\User;
use STS\Entities\Message;
use League\Fractal\TransformerAbstract;

class MessageTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Message $message)
    {
        $data = [
            'id' => $message->id, 
            'text' => $message->text,
            'created_at' => $message->created_at->toDateTimeString(),
            'user_id' => $message->user_id
        ];

        return $data;
    }
}
