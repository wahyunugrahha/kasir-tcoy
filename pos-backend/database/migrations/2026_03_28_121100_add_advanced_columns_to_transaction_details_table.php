<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->enum('line_discount_type', ['fixed', 'percent'])->nullable()->after('cogs_snapshot');
            $table->decimal('line_discount_rate', 8, 3)->default(0)->after('line_discount_type');
            $table->decimal('line_discount_amount', 15, 2)->default(0)->after('line_discount_rate');
            $table->decimal('line_tax_amount', 15, 2)->default(0)->after('line_discount_amount');
            $table->decimal('net_subtotal', 15, 2)->default(0)->after('line_tax_amount');
            $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name_snapshot')->nullable()->after('product_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
            $table->dropColumn([
                'variant_name_snapshot',
                'line_discount_type',
                'line_discount_rate',
                'line_discount_amount',
                'line_tax_amount',
                'net_subtotal',
            ]);
        });
    }
};
