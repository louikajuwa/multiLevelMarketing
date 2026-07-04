<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('type');   // CommissionType: direct | sponsor_bonus | adjustment
            $table->string('status')->default('pending'); // CommissionStatus: pending | paid | void
            $table->decimal('base_amount', 12, 2)->default(0); // amount the % was applied to
            $table->decimal('percent', 5, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);      // can be negative for adjustments/reversals
            $table->string('note')->nullable();                // reason for manual adjustments
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('partner_id');
            $table->index('order_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
