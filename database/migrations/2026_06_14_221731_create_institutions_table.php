<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('type_id')->constrained('institution_types');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->text('address');
            $table->decimal('discount_percentage', 5, 2);
            $table->string('contract_file')->nullable();
            $table->date('agreement_date');
            $table->date('agreement_expiry_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('created_by_marketer')->nullable()->constrained('users');
            $table->text('description')->nullable();
            $table->json('business_hours')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};