<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add body measurement fields to clients table.
     * These store the client's body measurements (not the dress size).
     * Dress sizes remain on the clothes table.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('breast_size')->nullable()->after('source');
            $table->string('waist_size')->nullable()->after('breast_size');
            $table->string('sleeve_size')->nullable()->after('waist_size');
            $table->string('hip_size')->nullable()->after('sleeve_size');
            $table->string('shoulder_size')->nullable()->after('hip_size');
            $table->string('length_size')->nullable()->after('shoulder_size');
            $table->text('measurement_notes')->nullable()->after('length_size');
            $table->date('last_measurement_date')->nullable()->after('measurement_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'breast_size',
                'waist_size',
                'sleeve_size',
                'hip_size',
                'shoulder_size',
                'length_size',
                'measurement_notes',
                'last_measurement_date',
            ]);
        });
    }
};






