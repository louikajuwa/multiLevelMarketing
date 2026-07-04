<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name'); // snapshot
            $table->unsignedInteger('quantity');
            $table->decimal('unit_retail_price', 12, 2);  // original
            $table->decimal('unit_discount', 12, 2);       // per-unit discount amount
            $table->decimal('unit_final_price', 12, 2);    // final per unit
            $table->decimal('line_total', 12, 2);          // unit_final_price * quantity
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
