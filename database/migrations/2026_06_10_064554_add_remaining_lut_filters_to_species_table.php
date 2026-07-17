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
        Schema::table('species', function (Blueprint $table) {
            $table->json('ntfp_lists')->nullable()->after('use_types');
            $table->json('timber_lists')->nullable()->after('ntfp_lists');
            $table->string('domestication')->nullable()->after('invasiveness_en');
            $table->string('domestication_en')->nullable()->after('domestication');
            $table->string('data_status')->nullable()->after('domestication_en');
            $table->string('data_status_en')->nullable()->after('data_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn([
                'ntfp_lists',
                'timber_lists',
                'domestication',
                'domestication_en',
                'data_status',
                'data_status_en',
            ]);
        });
    }
};
