<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->string('type')->nullable()->after('order_number');
            $table->foreignId('model_id')->nullable()->after('type')->constrained('cloth_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropForeign(['model_id']);
            $table->dropColumn(['type', 'model_id']);
        });
    }
};

