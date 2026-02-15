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
        if (! Schema::hasColumn('agent_conversations', 'guest_token')) {
            Schema::table('agent_conversations', function (Blueprint $table) {
                $table->string('guest_token', 64)->nullable()->after('user_id');
                $table->index(['guest_token', 'updated_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('agent_conversations', 'guest_token')) {
            Schema::table('agent_conversations', function (Blueprint $table) {
                $table->dropColumn('guest_token');
            });
        }
    }
};
