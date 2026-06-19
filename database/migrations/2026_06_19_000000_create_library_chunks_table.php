<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('library_chunks')) {
            Schema::create('library_chunks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('library_resource_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('chunk_index');
                $table->longText('content');
                $table->string('content_hash', 32)->index();
                $table->timestamps();
            });
        }

        // The pgvector column is PostgreSQL-only; SQLite (tests) skips it.
        if (Schema::getConnection()->getDriverName() === 'pgsql' && ! Schema::hasColumn('library_chunks', 'embedding')) {
            Schema::table('library_chunks', function (Blueprint $table) {
                $table->vector('embedding', dimensions: 1536)->nullable()->index();
            });
        }

        Schema::table('library_resources', function (Blueprint $table) {
            if (! Schema::hasColumn('library_resources', 'pdf_status')) {
                $table->string('pdf_status')->nullable()->index();
            }
            if (! Schema::hasColumn('library_resources', 'pdf_text_hash')) {
                $table->string('pdf_text_hash', 32)->nullable();
            }
            if (! Schema::hasColumn('library_resources', 'pdf_indexed_at')) {
                $table->timestamp('pdf_indexed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_chunks');

        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropColumn(['pdf_status', 'pdf_text_hash', 'pdf_indexed_at']);
        });
    }
};
