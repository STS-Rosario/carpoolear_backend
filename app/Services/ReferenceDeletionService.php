<?php

namespace STS\Services;

use STS\Models\References;
use STS\Models\User;
use Illuminate\Support\Facades\DB;

class ReferenceDeletionService
{
    /**
     * Delete a reference and all related data. Use only for references with no associated trips or ratings.
     *
     * @param References $reference
     * @return bool
     */
    public function deleteReference(References $reference): bool
    {
        return DB::transaction(function () use ($reference) {
            $referenceId = $reference->id;
            References::destroy($referenceId);
            return true;
        });
    }

    public function getUserReferences(User $user)
    {
        return $user->referencesReceived()->get();
    }
}