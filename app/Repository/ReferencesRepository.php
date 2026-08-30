<?php

namespace STS\Repository;

use STS\Models\References as ReferencesModel;

class ReferencesRepository
{
    public function create(ReferencesModel $reference)
    {
        return $reference->save();
    }

    public function get($userFromId, $userToId)
    {
        return ReferencesModel::where('user_id_from', $userFromId)
            ->where('user_id_to', $userToId)
            ->first();
    }

    public function update(ReferencesModel $reference)
    {
        return $reference->save();
    }
}
