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
        Schema::create('countries', function (Blueprint $table) {
            $table->id(); // id
            $table->string('name'); // اسم الدولة
            $table->string('code', 10); // رمز الدولة مثل "US"
            $table->string('currency_name')->nullable(); // اسم العملة
            $table->string('currency_symbol')->nullable(); // رمز العملة مثل "$"
            $table->string('image')->nullable(); // رابط أو مسار العلم
            $table->text('description')->nullable(); // وصف الدولة
            $table->boolean('active')->default(true); // الحالة
            $table->timestamps(); // created_at + updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
