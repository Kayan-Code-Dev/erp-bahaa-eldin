<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add measurements fields to cloth_order pivot table for tailoring items.
     */
    public function up(): void
    {
        Schema::table('cloth_order', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('notes'); // الكمية
            $table->decimal('paid', 12, 2)->default(0)->after('quantity'); // المبلغ المدفوع
            $table->decimal('remaining', 12, 2)->default(0)->after('paid'); // المبلغ المتبقي
            $table->string('sleeve_length')->nullable()->after('remaining'); // طول الكم
            $table->string('forearm')->nullable()->after('sleeve_length'); // الزند
            $table->string('shoulder_width')->nullable()->after('forearm'); // عرض الكتف
            $table->string('cuffs')->nullable()->after('shoulder_width'); // الإسوار
            $table->string('waist')->nullable()->after('cuffs'); // الوسط
            $table->string('chest_length')->nullable()->after('waist'); // طول الصدر
            $table->string('total_length')->nullable()->after('chest_length'); // الطول الكلي
            $table->string('hinch')->nullable()->after('total_length'); // الهش
            $table->string('dress_size')->nullable()->after('hinch'); // مقاس الفستان
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloth_order', function (Blueprint $table) {
            $table->dropColumn([
                'quantity',
                'paid',
                'remaining',
                'sleeve_length',
                'forearm',
                'shoulder_width',
                'cuffs',
                'waist',
                'chest_length',
                'total_length',
                'hinch',
                'dress_size',
            ]);
        });
    }
};

