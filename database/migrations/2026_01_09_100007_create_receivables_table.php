<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Receivables track money owed TO the business (customer debts).
     * This is separate from payments which track actual money received.
     * A receivable can be linked to an order and tracks the total amount owed.
     */
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->decimal('original_amount', 15, 2); // Original debt amount
            $table->decimal('paid_amount', 15, 2)->default(0); // Amount paid so far
            $table->decimal('remaining_amount', 15, 2); // Remaining balance (calculated)
            $table->date('due_date')->nullable(); // When payment is due
            $table->text('description');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'written_off'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['client_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('due_date');
            $table->index('status');
        });

        // Create receivable_payments pivot table to track individual payments against receivables
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained('receivables')->onDelete('cascade');
            $table->unsignedBigInteger('payment_id')->nullable(); // Optional link to payments table
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('cash'); // cash, card, transfer, check
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index(['receivable_id', 'payment_date']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
        Schema::dropIfExists('receivables');
    }
};

