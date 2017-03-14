<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Entities\Conversation;


class ConversationsTransformer extends TransformerAbstract
{

    /**
     * Turn this item object into a generic array
     *
     * @return array
     */
    public function transform(Conversation $conversation)
    {
        return [
            'id' => $conversation->id
        ];
    }

}