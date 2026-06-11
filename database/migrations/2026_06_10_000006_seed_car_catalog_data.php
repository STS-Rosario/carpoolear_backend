<?php

use Database\Seeders\CarCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new CarCatalogSeeder)->run();
    }

    public function down(): void
    {
        // Catalog rows may already be referenced by user cars; leave data in place.
    }
};
