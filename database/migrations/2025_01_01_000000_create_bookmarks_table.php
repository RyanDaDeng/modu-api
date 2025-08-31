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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('comic_id');
            $table->string('comic_name');
            $table->string('chapter_id');
            $table->timestamps();
            
            // Ensure a user can only have one bookmark per comic
            $table->unique(['user_id', 'comic_id']);
            
            // Index for faster queries
            $table->index('user_id');
            $table->index('comic_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};