<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'qris', 'debit', 'credit_card', 'e_wallet', 'bank_transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
