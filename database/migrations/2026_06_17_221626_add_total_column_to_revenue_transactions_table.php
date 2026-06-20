<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_transactions', function (Blueprint $table) {
            // ✅ إضافة عمود total لحساب مجموع ربح الشركة
            $table->decimal('total', 12, 2)->default(0)->after('net_amount');
            
            // ✅ إضافة مؤشر للبحث السريع
            $table->index('total');
        });
    }

    public function down(): void
    {
        Schema::table('revenue_transactions', function (Blueprint $table) {
            $table->dropColumn('total');
        });
    }
};