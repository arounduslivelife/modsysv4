<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bot_tele_sessions')) {
            Schema::create('bot_tele_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('chat_id')->index();
                $table->string('acosys_user_id', 50);
                $table->string('acosys_user_name', 100)->nullable();
                $table->string('acosys_user_role', 50)->nullable();
                $table->string('state', 50)->default('idle');
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'chat_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_tele_sessions');
    }
};
