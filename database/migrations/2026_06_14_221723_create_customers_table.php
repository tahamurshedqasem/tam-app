<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('membership_number', 10)->unique();
            $table->text('address')->nullable();
            $table->string('identity_image')->nullable();
            $table->string('personal_image')->nullable();
            $table->json('fingerprint_data')->nullable();
            $table->foreignId('created_by_marketer')->nullable()->constrained('users');
            $table->date('membership_expiry_date')->nullable();
            $table->decimal('total_discount_saved', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};