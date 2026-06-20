<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->unique();
            $table->string('password');
            $table->enum('role', [
                'admin',
                'customer',
                'customer_marketer',
                'institution_marketer',
                'institution_owner'
            ]);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};