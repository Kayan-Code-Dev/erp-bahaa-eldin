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
        Schema::table('orders', function (Blueprint $table) {
            $table->text('order_notes')->nullable()->after('visit_datetime');
            $table->enum('discount_type', ['none', 'percentage', 'fixed'])->nullable()->after('order_notes');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_notes', 'discount_type', 'discount_value']);
        });
    }
};
