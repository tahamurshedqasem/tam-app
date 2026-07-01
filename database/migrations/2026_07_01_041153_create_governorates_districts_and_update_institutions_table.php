<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول المحافظات
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // جدول المناطق
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->foreignId('governorate_id')->constrained('governorates')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // تحديث جدول المؤسسات
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->onDelete('set null');
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('set null');
            $table->string('governorate_name')->nullable();
            $table->string('district_name')->nullable();
            
            // ✅ إضافة فهارس للبحث السريع
            $table->index(['governorate_id', 'district_id']);
            $table->index('governorate_name');
            $table->index('district_name');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['district_id']);
            $table->dropColumn(['governorate_id', 'district_id', 'governorate_name', 'district_name']);
        });

        Schema::dropIfExists('districts');
        Schema::dropIfExists('governorates');
    }
};