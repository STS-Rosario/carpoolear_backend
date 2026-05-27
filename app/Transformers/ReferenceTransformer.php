<?php

namespace STS\Transformers;

use League\Fractal\TransformerAbstract;
use STS\Models\References;

class ReferenceTransformer extends TransformerAbstract
{
    public function transform(References $reference): array
    {
        return [
            'id' => $reference->id,
            'user_id_from' => $reference->user_id_from,
            'user_id_to' => $reference->user_id_to,
            'comment' => $reference->comment,
        ];
    }
}
