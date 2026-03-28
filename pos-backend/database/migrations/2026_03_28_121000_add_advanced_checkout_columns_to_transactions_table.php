<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percent'])->default('fixed')->after('discount');
            $table->decimal('discount_rate', 8, 3)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate');
            $table->decimal('tax_rate', 8, 3)->default(0)->after('tax');
            $table->boolean('tax_included')->default(false)->after('tax_rate');
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('amount_paid');
            $table->string('payment_method')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_method')->nullable(false)->change();
            $table->dropColumn([
                'discount_type',
                'discount_rate',
                'discount_amount',
                'tax_rate',
                'tax_included',
                'refunded_amount',
            ]);
        });
    }
};
