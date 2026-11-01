<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Module;
use App\Models\Account;
use App\Models\AccountModule;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // ================= MODUL ACOSYS V4 (DIKEMBALIKAN UTUH) =================
            [
                'code' => 'pengingat_piutang',
                'name' => 'Pengingat Piutang WA',
                'acosys_version' => 'v4',
                'description' => 'Kirim notifikasi otomatis pengingat jatuh tempo piutang pelanggan langsung via WhatsApp Gateway dengan nomor sender Anda sendiri (Acosys V4).',
                'icon' => 'bell-ring',
                'route' => 'modules.pengingat-piutang.index',
                'price_info' => 'Rp 99.000 / bln',
                'sort_order' => 1,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'katalog_online',
                'name' => 'Katalog Web & Self-Order',
                'acosys_version' => 'v4',
                'description' => 'Publikasikan katalog produk Acosys V4 ke web publik agar pelanggan dapat melihat katalog dan memesan sendiri secara mandiri.',
                'icon' => 'shopping-bag',
                'route' => 'modules.katalog-online.index',
                'price_info' => 'Rp 149.000 / bln',
                'sort_order' => 2,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'laporan_analitik',
                'name' => 'Dashboard Analitik Owner',
                'acosys_version' => 'v4',
                'description' => 'Visualisasi performa penjualan, margin laba kotor, dan rekomendasi restock cerdas (EOQ) langsung dari HP atau browser.',
                'icon' => 'line-chart',
                'route' => 'modules.laporan-analitik.index',
                'price_info' => 'Rp 79.000 / bln',
                'sort_order' => 3,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'absensi_karyawan',
                'name' => 'Absensi & Komisi Sales',
                'acosys_version' => 'v4',
                'description' => 'Pantau kehadiran kasir dan staf toko serta hitung komisi per transaksi secara otomatis di Acosys V4.',
                'icon' => 'users-round',
                'route' => 'modules.absensi.index',
                'price_info' => 'Rp 69.000 / bln',
                'sort_order' => 4,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // ================= MODUL ACOSYS V8 =================
            [
                'code' => 'v8_buku_besar',
                'name' => 'Cek Buku Besar (Bot Tele)',
                'acosys_version' => 'v8',
                'description' => 'Monitoring saldo buku besar kasir (akun paymenttype) secara realtime langsung dari database Acosys V8 via Bot Telegram.',
                'icon' => 'credit-card',
                'route' => 'modules.v8-buku-besar.index',
                'price_info' => 'Rp 49.000 / bln',
                'sort_order' => 10,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'v8_jurnal_tele',
                'name' => 'Jurnal Tele',
                'acosys_version' => 'v8',
                'description' => 'Input transaksi jurnal umum kas & bank langsung dari obrolan Bot Telegram terhubung database Acosys V8.',
                'icon' => 'edit',
                'route' => 'modules.v8-jurnal-tele.index',
                'price_info' => 'Rp 69.000 / bln',
                'sort_order' => 11,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($modules as $mod) {
            DB::table('modules')->updateOrInsert(
                ['code' => $mod['code']],
                $mod
            );
        }

        // Hapus modul V8 yang tidak ada di daftar
        $allowedV8 = ['v8_buku_besar', 'v8_jurnal_tele'];
        DB::table('modules')->where('acosys_version', 'v8')->whereNotIn('code', $allowedV8)->delete();

        // Pastikan akun pertama memiliki modul aktif
        $account = Account::first();
        if ($account) {
            $v4Mod = Module::where('code', 'pengingat_piutang')->first();
            if ($v4Mod) {
                AccountModule::updateOrCreate(
                    ['account_id' => $account->id, 'module_id' => $v4Mod->id],
                    ['is_active' => true, 'starts_at' => now(), 'expires_at' => now()->addYear()]
                );
            }

            foreach ($allowedV8 as $v8Code) {
                $v8Mod = Module::where('code', $v8Code)->first();
                if ($v8Mod) {
                    AccountModule::updateOrCreate(
                        ['account_id' => $account->id, 'module_id' => $v8Mod->id],
                        ['is_active' => true, 'starts_at' => now(), 'expires_at' => now()->addYear()]
                    );
                }
            }
        }
    }
}
