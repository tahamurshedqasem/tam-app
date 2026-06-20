<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['customer_registration', 'institution_registration', 'renewal', 'commission_payment']);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('total_commissions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->json('commission_breakdown')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('institution_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('marketer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('currency')->default('YER');
            $table->timestamp('transaction_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index('transaction_date');
            $table->index('customer_id');
            $table->index('institution_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_transactions');
    }
};