<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bot_tele_sessions') && !Schema::hasColumn('bot_tele_sessions', 'session_data')) {
            Schema::table('bot_tele_sessions', function (Blueprint $table) {
                $table->longText('session_data')->nullable()->after('state');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bot_tele_sessions') && Schema::hasColumn('bot_tele_sessions', 'session_data')) {
            Schema::table('bot_tele_sessions', function (Blueprint $table) {
                $table->dropColumn('session_data');
            });
        }
    }
};
