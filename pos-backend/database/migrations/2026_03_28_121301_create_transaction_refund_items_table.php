<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_refund_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('transaction_detail_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('line_refund_total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_refund_items');
    }
};
