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
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('comic_id');
            $table->string('name');
            $table->string('author')->nullable();
            $table->timestamps();
            
            // Ensure a user can't collect the same comic twice
            $table->unique(['user_id', 'comic_id']);
            
            // Index for faster queries
            $table->index('user_id');
            $table->index('comic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};