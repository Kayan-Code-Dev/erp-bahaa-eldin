<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // Add branch_id to model_has_permissions
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('model_id');
            $table->index(['model_id', 'model_type', 'branch_id']);
        });

        // Add branch_id to model_has_roles
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('model_id');
            $table->index(['model_id', 'model_type', 'branch_id']);
        });

        // Add branch_id to roles
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->index('branch_id');

            // Drop old unique index on name + guard_name
            $table->dropUnique('roles_name_guard_name_unique'); // تأكد الاسم في DB
            $table->unique(['name', 'guard_name', 'branch_id']);
        });
    }

    public function down(): void
    {
        // Remove unique index on name + guard_name + branch_id
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name', 'branch_id']);
            $table->unique(['name', 'guard_name']);
        });

        // Drop branch_id and indexes if needed
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropIndex(['model_id', 'model_type', 'branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex(['model_id', 'model_type', 'branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('roles_branch_id_index');
            $table->dropColumn('branch_id');
        });
    }
};
