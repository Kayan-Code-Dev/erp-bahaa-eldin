<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->decimal('payment_amount', 12, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table) {
            $table->dropColumn('payment_amount');
        });
    }
};

