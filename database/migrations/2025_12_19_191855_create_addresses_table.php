<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street');
            $table->string('building');
            $table->string('notes')->nullable();
            $table->foreignId('city_id')->constrained('cities')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
        
        // Add foreign key constraint for clients.address_id -> addresses.id
        Schema::table('clients', function (Blueprint $table) {
            $table->foreign('address_id')->references('id')->on('addresses')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraint before dropping addresses table
        // Check if clients table exists first
        if (Schema::hasTable('clients')) {
            try {
                Schema::table('clients', function (Blueprint $table) {
                    $table->dropForeign(['address_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, ignore the error
            }
        }
        
        Schema::dropIfExists('addresses');
    }
};
