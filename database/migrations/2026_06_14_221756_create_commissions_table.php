<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['customer_marketer', 'institution_marketer'])->default('customer_marketer');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->string('reason')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('discount_transactions')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('institution_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('currency')->default('YER');
            $table->decimal('service_fee', 12, 2)->default(3000);
            $table->decimal('customer_discount', 12, 2)->default(0);
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['role', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};