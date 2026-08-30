<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('available');
            $table->timestamp('assigned_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('redemption_note')->nullable();
            $table->timestamps();
            $table->unique(['coupon_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void { Schema::dropIfExists('coupon_user'); }
};
