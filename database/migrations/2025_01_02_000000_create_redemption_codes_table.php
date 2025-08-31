<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('redemption_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('vip'); // Type of redemption (vip, coins, etc.)
            $table->integer('value'); // Days for VIP, amount for coins, etc.
            $table->boolean('is_active')->default(true);
            $table->string('reference')->nullable(); // External reference
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemption_codes');
    }
};