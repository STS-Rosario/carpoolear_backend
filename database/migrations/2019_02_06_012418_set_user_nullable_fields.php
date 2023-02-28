<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SetUserNullableFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nro_doc', 15)->nullable()->change();
            $table->string('description', 1000)->nullable()->change();
            $table->string('mobile_phone', 50)->nullable()->change();
            $table->string('image', 255)->nullable()->change();
            $table->boolean('banned')->default(false)->change();
            $table->boolean('is_admin')->default(false)->change();
            $table->boolean('active')->default(false)->change();
            $table->datetime('last_connection')->nullable()->change();
            $table->boolean('has_pin')->default(false)->change();
            $table->boolean('is_member')->default(false)->change();
            $table->boolean('monthly_donate')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nro_doc', 15)->nullable(false)->change();
            $table->string('description', 1000)->nullable(false)->change();
            $table->string('mobile_phone', 50)->nullable(false)->change();
            $table->string('image', 255)->nullable(false)->change();
            DB::statement('ALTER TABLE users ALTER COLUMN banned DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN is_admin DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN active DROP DEFAULT');
            $table->datetime('last_connection')->nullable(false)->change();
            DB::statement('ALTER TABLE users ALTER COLUMN has_pin DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN is_member DROP DEFAULT');
            DB::statement('ALTER TABLE users ALTER COLUMN monthly_donate DROP DEFAULT');
        });
    }
}
