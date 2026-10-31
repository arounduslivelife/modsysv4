<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bot_tele_settings')) {
            Schema::create('bot_tele_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('connection_id')->nullable()->constrained('acosys_connections')->nullOnDelete();
                $table->text('bot_token')->nullable(); // encrypted
                $table->string('bot_username')->nullable();
                $table->string('chat_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('notify_daily_sales')->default(true);
                $table->time('daily_sales_time')->default('21:00:00');
                $table->boolean('notify_overdue_debt')->default(true);
                $table->boolean('notify_low_stock')->default(false);
                $table->integer('stock_threshold')->default(5);
                $table->timestamp('last_test_at')->nullable();
                $table->boolean('last_test_ok')->default(false);
                $table->text('last_test_message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_tele_settings');
    }
};
