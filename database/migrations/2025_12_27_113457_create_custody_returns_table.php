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
        Schema::create('custody_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custody_id')->constrained('custodies')->onDelete('cascade');
            $table->foreignId('order_return_id')->nullable()->constrained('order_returns')->onDelete('set null');
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->dateTime('returned_at')->nullable();
            $table->string('return_proof_photo')->nullable();
            $table->string('reason_of_kept')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_id_number')->nullable();
            $table->dateTime('customer_signature_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custody_returns');
    }
};
