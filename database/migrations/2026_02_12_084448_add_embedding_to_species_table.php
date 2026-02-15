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
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('species') || Schema::hasColumn('species', 'embedding')) {
            return;
        }

        Schema::table('species', function (Blueprint $table) {
            $table->vector('embedding', dimensions: 1536)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('species') || ! Schema::hasColumn('species', 'embedding')) {
            return;
        }

        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
