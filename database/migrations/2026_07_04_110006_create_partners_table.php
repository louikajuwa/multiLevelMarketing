<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_level_id')->constrained('partner_levels');

            // Admin per-partner override of the level's default discount.
            $table->decimal('discount_override_percent', 5, 2)->nullable();

            // v1: sponsor_id == parent_id (one edge). Immutable after creation.
            $table->foreignId('sponsor_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('partners')->nullOnDelete();

            // Materialized path of ancestor partner ids, e.g. "/1/4/9/". Bounded to 4 levels.
            $table->string('path', 191)->default('/');
            $table->unsignedTinyInteger('depth')->default(1); // 1..4

            $table->unsignedInteger('direct_referral_count')->default(0);
            $table->unsignedInteger('total_downline_count')->default(0);

            $table->string('status')->default('active');
            $table->timestamps();

            $table->index('sponsor_id');
            $table->index('parent_id');
            $table->index('path');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
