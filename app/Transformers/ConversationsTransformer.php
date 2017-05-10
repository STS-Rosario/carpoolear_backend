<?php

namespace STS\Transformers;

use STS\User;
use STS\Entities\Conversation;
use League\Fractal\TransformerAbstract;

class ConversationsTransformer extends TransformerAbstract
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
    public function transform(Conversation $conversation)
    {
        $data = [
            'id' => $conversation->id,
            'type' => $conversation->type
        ];

        switch ($conversation->type) {
            case Conversation::TYPE_PRIVATE_CONVERSATION:
                $width = $conversation->users()->where('id', '<>', $this->user->id)->first();
                $data['title'] = $width->name;
                $data['image'] = $width->image ? '/image/profile/'.$width->image : '';
                break;
            default:
                $data['title'] = $conversation->title;
                $data['image'] = '';
                break;
        }

        $data['unread'] = ! $conversation->read($this->user);
        $data['update_at'] = $conversation->updated_at->toDateTimeString();

        $data['users'] = [];
        foreach($conversation->users as $u) {
            $data['users'][] = [
                'id' => $u->id,
                'name' => $u->name,
                'last_connection' => $u->last_connection->toDateTimeString()
            ];
        }

        return $data;
    }
}
