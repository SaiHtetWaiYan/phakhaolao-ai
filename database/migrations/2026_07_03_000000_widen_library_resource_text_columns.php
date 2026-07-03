<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            // Author can be a long list of names; file/source URLs can exceed 255.
            $table->text('author')->nullable()->change();
            $table->text('file_url')->nullable()->change();
            $table->text('source_url')->nullable()->change();
            $table->text('featured_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->string('author')->nullable()->change();
            $table->string('file_url')->nullable()->change();
            $table->string('source_url')->nullable()->change();
            $table->string('featured_image')->nullable()->change();
        });
    }
};
