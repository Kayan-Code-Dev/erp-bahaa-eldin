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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_manager_id')->nullable()->constrained('branch_managers')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('email', 100)->unique();
            $table->string('phone', 45)->unique();
            $table->string('password')->nullable();
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('blocked')->default(0);
            $table->enum('status', ['inactive', 'active', 'suspended'])->default('inactive');
            $table->text('fcm_token')->nullable();
            $table->string('ip_address')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('last_logout')->nullable();
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
        Schema::dropIfExists('branches');
    }
};
