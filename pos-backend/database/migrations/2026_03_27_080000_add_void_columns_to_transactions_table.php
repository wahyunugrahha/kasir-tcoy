<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('amount_paid');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->index('is_voided');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['is_voided']);
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['is_voided', 'voided_at', 'void_reason']);
        });
    }
};
