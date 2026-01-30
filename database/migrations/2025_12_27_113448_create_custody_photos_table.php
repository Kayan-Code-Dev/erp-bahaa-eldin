<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custody_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custody_id')->constrained('custodies')->onDelete('cascade');
            $table->string('photo_path');
            $table->enum('photo_type', ['custody_photo', 'id_photo', 'acknowledgement_receipt'])->default('custody_photo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custody_photos');
    }
};
