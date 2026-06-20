<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');
            $table->foreignId('institution_owner_id')->constrained('users');
            $table->decimal('discount_percentage', 5, 2);
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->decimal('discounted_amount', 10, 2)->nullable();
            $table->decimal('amount_saved', 10, 2)->nullable();
            $table->string('transaction_receipt')->nullable();
            $table->dateTime('transaction_date');
            $table->text('notes')->nullable();
            $table->string('verification_method')->default('qr');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_transactions');
    }
};