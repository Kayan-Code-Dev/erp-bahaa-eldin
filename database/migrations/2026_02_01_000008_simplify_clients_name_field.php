<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Simplify client name: merge first_name, middle_name, last_name into single 'name' field
     */
    public function up(): void
    {
        // Add new 'name' column
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->after('id')->nullable();
        });

        // Migrate existing data: concatenate first_name + middle_name + last_name
        DB::statement("UPDATE clients SET name = CONCAT_WS(' ', first_name, middle_name, last_name)");

        // Make name required and drop old columns
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn(['first_name', 'middle_name', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old columns
        Schema::table('clients', function (Blueprint $table) {
            $table->string('first_name')->after('id')->nullable();
            $table->string('middle_name')->after('first_name')->nullable();
            $table->string('last_name')->after('middle_name')->nullable();
        });

        // Migrate data back (split name into parts - best effort)
        // Note: This is a lossy operation
        DB::statement("UPDATE clients SET first_name = SUBSTRING_INDEX(name, ' ', 1)");
        DB::statement("UPDATE clients SET middle_name = IF(
            LENGTH(name) - LENGTH(REPLACE(name, ' ', '')) >= 2,
            SUBSTRING_INDEX(SUBSTRING_INDEX(name, ' ', 2), ' ', -1),
            ''
        )");
        DB::statement("UPDATE clients SET last_name = IF(
            LENGTH(name) - LENGTH(REPLACE(name, ' ', '')) >= 1,
            SUBSTRING_INDEX(name, ' ', -1),
            ''
        )");

        // Drop name column
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

