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
            $table->string('iucn_status_en')->nullable()->after('iucn_status');
            $table->string('national_conservation_status_en')->nullable()->after('national_conservation_status');
            $table->string('native_status_en')->nullable()->after('native_status');
            $table->string('invasiveness_en')->nullable()->after('invasiveness');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('species', function (Blueprint $table) {
            $table->dropColumn([
                'iucn_status_en',
                'national_conservation_status_en',
                'native_status_en',
                'invasiveness_en',
            ]);
        });
    }
};
