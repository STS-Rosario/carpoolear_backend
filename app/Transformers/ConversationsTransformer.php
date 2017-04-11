<?php

namespace STS\Transformers;

use STS\Entities\Conversation;
use League\Fractal\TransformerAbstract;

class ConversationsTransformer extends TransformerAbstract
{
    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Conversation $conversation)
    {
        return [
            'id' => $conversation->id,
        ];
    }
}
