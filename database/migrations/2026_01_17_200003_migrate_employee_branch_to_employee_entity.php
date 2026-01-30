<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates existing data from employee_branch to the new employee_entity table.
     */
    public function up(): void
    {
        // Check if employee_branch table exists and has data
        if (Schema::hasTable('employee_branch')) {
            $existingAssignments = DB::table('employee_branch')->get();

            foreach ($existingAssignments as $assignment) {
                // Check if this assignment already exists in employee_entity
                $exists = DB::table('employee_entity')
                    ->where('employee_id', $assignment->employee_id)
                    ->where('entity_type', 'branch')
                    ->where('entity_id', $assignment->branch_id)
                    ->exists();

                if (!$exists) {
                    DB::table('employee_entity')->insert([
                        'employee_id' => $assignment->employee_id,
                        'entity_type' => 'branch',
                        'entity_id' => $assignment->branch_id,
                        'is_primary' => $assignment->is_primary ?? false,
                        'assigned_at' => $assignment->assigned_at,
                        'unassigned_at' => $assignment->unassigned_at ?? null,
                        'created_at' => $assignment->created_at ?? now(),
                        'updated_at' => $assignment->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove only the migrated branch entries (not any new ones)
        DB::table('employee_entity')
            ->where('entity_type', 'branch')
            ->delete();
    }
};

