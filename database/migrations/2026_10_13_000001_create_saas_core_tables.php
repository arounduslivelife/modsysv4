<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Akun Usaha / Tenant
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active'); // active, suspended
            $table->timestamps();
        });

        // 2. Anggota Akun (Multi-staff)
        Schema::create('account_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('staff'); // owner, staff
            $table->string('status')->default('active'); // active, invited, suspended
            $table->timestamps();

            $table->unique(['account_id', 'user_id']);
        });

        // 3. Undangan Staff via Email
        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('staff');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 4. Katalog Modul SaaS
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. 'pengingat_piutang'
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->default('package'); // lucide icon name
            $table->string('route')->nullable(); // route name
            $table->string('price_info')->nullable(); // e.g. 'Rp 99.000 / bln'
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // 5. Modul yang Aktif per Akun (diatur oleh Admin SaaS)
        Schema::create('account_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['account_id', 'module_id']);
        });

        // 6. Permintaan Beli / Aktivasi Modul (klik menu abu-abu)
        Schema::create('module_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 7. Pengaturan Koneksi DB Acosys (bisa banyak DB per akun)
        Schema::create('acosys_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('label'); // e.g. "Toko Pusat", "Cabang Bandung"
            $table->string('host')->default('127.0.0.1');
            $table->integer('port')->default(3306);
            $table->string('database');
            $table->string('username');
            $table->text('password'); // encrypted
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->default(false);
            $table->text('test_message')->nullable();
            $table->timestamps();
        });

        // 8. Perangkat WA (WA Gateway Device)
        Schema::create('wa_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('label')->default('WhatsApp Utama');
            $table->string('phone')->nullable();
            $table->string('session_key')->unique();
            $table->string('status')->default('disconnected'); // disconnected, scanning, connected
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        // 9. Pengaturan Modul Pengingat Piutang (per koneksi DB)
        Schema::create('reminder_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('acosys_connections')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('wa_devices')->nullOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('recipient_mode')->default('both'); // customer, fixed, both
            $table->text('fixed_numbers')->nullable(); // comma separated numbers
            $table->time('send_time')->default('09:00:00');
            $table->json('rules')->nullable(); // [{type: 'due_before', days: 3}, ...]
            $table->timestamps();
        });

        // 10. Template Pesan Pengingat
        Schema::create('reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('customer'); // customer, recap
            $table->text('body');
            $table->timestamps();
        });

        // 11. Override Kontak Pelanggan & Opt-Out
        Schema::create('reminder_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('acosys_connections')->cascadeOnDelete();
            $table->string('customer_id', 64);
            $table->string('phone_override', 32)->nullable();
            $table->boolean('opt_out')->default(false);
            $table->timestamps();

            $table->unique(['connection_id', 'customer_id']);
        });

        // 12. Log Riwayat Pengiriman WA
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('acosys_connections')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('wa_devices')->nullOnDelete();
            $table->string('customer_id', 64)->nullable();
            $table->string('salesid', 64)->nullable();
            $table->string('phone', 32);
            $table->text('body');
            $table->string('status')->default('pending'); // pending, queued, sent, failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 13. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('reminder_contacts');
        Schema::dropIfExists('reminder_templates');
        Schema::dropIfExists('reminder_settings');
        Schema::dropIfExists('wa_devices');
        Schema::dropIfExists('acosys_connections');
        Schema::dropIfExists('module_requests');
        Schema::dropIfExists('account_modules');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('account_members');
        Schema::dropIfExists('accounts');
    }
};
