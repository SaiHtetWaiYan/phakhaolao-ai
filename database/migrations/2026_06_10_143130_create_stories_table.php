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
        Schema::create('stories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('source_id')->unique();
            $table->string('language', 5)->default('en');
            $table->string('slug')->nullable();
            $table->string('title');
            $table->string('authors')->nullable();
            $table->text('summary')->nullable();
            $table->longText('story')->nullable();
            $table->string('image_credits')->nullable();
            $table->json('story_types')->nullable();

            $table->string('source_url')->nullable();
            $table->timestamp('source_modified_at')->nullable();
            $table->string('content_hash')->nullable()->index();

            $table->timestamps();

            $table->index(['language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
