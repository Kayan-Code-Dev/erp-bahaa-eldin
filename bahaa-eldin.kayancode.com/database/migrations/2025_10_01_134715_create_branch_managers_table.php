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
        Schema::create('branch_managers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name', 45);
            $table->string('last_name', 45);
            $table->string('email', 45)->unique();
            $table->string('phone', 45)->unique();
            $table->string('branch_number')->unique();
            $table->string('branch_name')->unique();
            $table->string('password')->nullable();
            $table->string('id_number', 45)->unique();
            $table->string('image', 255)->nullable();
            $table->boolean('blocked')->default(0);
            $table->dateTime('last_login')->nullable();
            $table->dateTime('last_logout')->nullable();
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['inactive', 'active', 'suspended'])->default('inactive');
            $table->foreignId('city_id')->constrained('cities')->onDelete('cascade');
            $table->text('fcm_token')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('otp_code')->nullable();
            $table->timestamp('code_expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_managers');
    }
};
