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
        Schema::create('library_resources', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('source_id')->unique();
            $table->string('language', 5)->default('en');
            $table->string('slug')->nullable();
            $table->string('title');
            $table->longText('description')->nullable();

            $table->string('resource_type')->nullable();
            $table->string('resource_language')->nullable();
            $table->string('access_right')->nullable();
            $table->boolean('featured')->default(false);
            $table->json('topics')->nullable();
            $table->json('provinces')->nullable();

            $table->string('featured_image')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('source_modified_at')->nullable();
            $table->string('content_hash')->nullable()->index();

            $table->timestamps();

            $table->index(['language']);
            $table->index(['resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_resources');
    }
};
