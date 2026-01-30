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
        Schema::create('employee_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('username', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('mobile', 20)->nullable()->unique();
            $table->string('password')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('blocked')->default(0);
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
        Schema::dropIfExists('employee_logins');
    }
};
