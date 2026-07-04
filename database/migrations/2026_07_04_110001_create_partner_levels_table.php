<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('level')->unique(); // 1..4
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->nullable();      // falls back to settings default
            $table->decimal('sponsor_bonus_percent', 5, 2)->nullable();   // falls back to settings default
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_levels');
    }
};
