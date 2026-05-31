<?php

namespace STS\Services;

use STS\Models\User;

class UserMigrationFieldMerger
{
    /** @var list<string> */
    public const MERGEABLE_FIELDS = [
        'email',
        'password',
        'nro_doc',
        'mobile_phone',
        'created_at',
    ];

    /** @var array<string, 'removed'|'kept'> */
    public const DEFAULT_FIELD_SOURCES = [
        'email' => 'removed',
        'password' => 'kept',
        'nro_doc' => 'removed',
        'mobile_phone' => 'kept',
        'created_at' => 'removed',
    ];

    /**
     * @param  array<string, 'removed'|'kept'>  $fieldSources
     */
    public function apply(User $kept, User $removed, array $fieldSources = []): void
    {
        $resolved = array_merge(self::DEFAULT_FIELD_SOURCES, $fieldSources);

        foreach (self::MERGEABLE_FIELDS as $field) {
            $source = $resolved[$field];
            $from = $source === 'removed' ? $removed : $kept;
            $kept->{$field} = $from->{$field};
        }

        $kept->save();
    }
}
