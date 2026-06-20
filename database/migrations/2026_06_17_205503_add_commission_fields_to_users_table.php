<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('total_commission', 12, 2)->default(0)->after('role');
            $table->decimal('pending_commission', 12, 2)->default(0)->after('total_commission');
            $table->decimal('paid_commission', 12, 2)->default(0)->after('pending_commission');
            $table->integer('customers_count')->default(0)->after('paid_commission');
            $table->integer('institutions_count')->default(0)->after('customers_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'total_commission',
                'pending_commission',
                'paid_commission',
                'customers_count',
                'institutions_count'
            ]);
        });
    }
};