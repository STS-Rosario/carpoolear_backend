<?php

namespace Tests\Unit\Admin;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminRoleSchemaTest extends TestCase
{
    public function test_users_table_has_nullable_admin_role_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'admin_role'));
    }
}
