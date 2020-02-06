<?php

namespace STS\Contracts\Repository;
use STS\Entities\References as ReferencesModel;

interface IReferencesRepository
{
    public function create(ReferencesModel $reference);
}