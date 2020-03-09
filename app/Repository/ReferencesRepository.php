<?php

namespace STS\Repository;

use STS\Entities\References as ReferencesModel;
use STS\Contracts\Repository\IReferencesRepository;

class ReferencesRepository implements IReferencesRepository
{
    public function create(ReferencesModel $reference)
    {
        return $reference->save();
    }
}