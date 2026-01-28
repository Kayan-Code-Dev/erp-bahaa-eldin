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
        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->foreignId('subCategories_id')->nullable()->constrained('sub_categories')->nullOnDelete(); // الفئة (قابل تكون فارغة)
            $table->text('notes')->nullable(); // ملاحظات
            $table->enum('status', ['pending', 'approved', 'rejected', 'arrived'])->default('pending');
            $table->unsignedBigInteger('requested_by_id');
            $table->string('requested_by_type'); // 'employee' أو 'branch'
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->string('approved_by_type')->nullable();
            $table->dateTime('arrival_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transfers');
    }
};
