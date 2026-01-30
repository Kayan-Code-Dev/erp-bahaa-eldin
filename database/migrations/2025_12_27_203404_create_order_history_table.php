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
        Schema::create('order_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('field_changed')->nullable()->comment('Field that was changed (e.g., status, total_price, items)');
            $table->text('old_value')->nullable()->comment('Previous value before change');
            $table->text('new_value')->nullable()->comment('New value after change');
            $table->string('change_type')->comment('Type of change: created, updated, item_added, item_removed, item_updated, status_changed, payment_added, payment_updated, payment_canceled, delivered, returned, finished, etc.');
            $table->text('description')->nullable()->comment('Human-readable description of the change');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null')->comment('User who made the change');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_history');
    }
};
