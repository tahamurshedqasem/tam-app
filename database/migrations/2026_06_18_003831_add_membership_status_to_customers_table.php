<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // ✅ إضافة عمود membership_status إذا لم يكن موجوداً
            if (!Schema::hasColumn('customers', 'membership_status')) {
                $table->string('membership_status')->default('active')->after('membership_expiry_date');
            }
            
            // ✅ إضافة عمود status كبديل
            if (!Schema::hasColumn('customers', 'status')) {
                $table->string('status')->default('active')->after('membership_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['membership_status', 'status']);
        });
    }
};