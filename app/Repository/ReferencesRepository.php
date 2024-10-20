<?php

namespace STS\Repository;

use STS\Models\References as ReferencesModel;

class ReferencesRepository
{
    public function create(ReferencesModel $reference)
    {
        return $reference->save();
    }
}