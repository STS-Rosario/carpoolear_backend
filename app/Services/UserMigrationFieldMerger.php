<?php

namespace STS\Services;

use STS\Models\User;

class UserMigrationFieldMerger
{
    /**
     * @param  array<string, 'removed'|'kept'>  $fieldSources
     */
    public function apply(User $kept, User $removed, array $fieldSources): void
    {
        $fields = ['email', 'password', 'nro_doc', 'mobile_phone', 'created_at'];

        foreach ($fields as $field) {
            $source = $fieldSources[$field] ?? null;
            if ($source === null) {
                continue;
            }

            $from = $source === 'removed' ? $removed : $kept;
            $kept->{$field} = $from->{$field};
        }

        $kept->save();
    }
}
