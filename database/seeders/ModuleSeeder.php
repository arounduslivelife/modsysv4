<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'code' => 'pengingat_piutang',
                'name' => 'Pengingat Piutang WA',
                'description' => 'Kirim notifikasi otomatis pengingat jatuh tempo piutang pelanggan langsung via WhatsApp Gateway dengan nomor sender Anda sendiri.',
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
                'description' => 'Publikasikan katalog produk Acosys ke web publik agar pelanggan dapat melihat katalog dan memesan sendiri secara mandiri.',
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
                'description' => 'Pantau kehadiran kasir dan staf toko serta hitung komisi per transaksi secara otomatis.',
                'icon' => 'users-round',
                'route' => 'modules.absensi.index',
                'price_info' => 'Rp 69.000 / bln',
                'sort_order' => 4,
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
    }
}
