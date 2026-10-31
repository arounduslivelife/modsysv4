<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // ================= MODUL ACOSYS V4 =================
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

            // ================= MODUL ACOSYS V8 =================
            [
                'code' => 'pengingat_piutang_v8',
                'name' => 'Pengingat Piutang WA V8',
                'acosys_version' => 'v8',
                'description' => 'Otomasi penagihan piutang dan invoice pelanggan via WhatsApp Gateway langsung dari database Acosys V8.',
                'icon' => 'bell-ring',
                'route' => 'modules.pengingat-piutang.index',
                'price_info' => 'Rp 99.000 / bln',
                'sort_order' => 10,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'absensi_kiosk_v8',
                'name' => 'Absensi Wajah & Kiosk V8',
                'acosys_version' => 'v8',
                'description' => 'Integrasi data kehadiran karyawan, biometric face recognition, dan kiosk device dari database Acosys V8.',
                'icon' => 'users-round',
                'route' => 'modules.absensi.index',
                'price_info' => 'Rp 129.000 / bln',
                'sort_order' => 11,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'crm_pipeline_v8',
                'name' => 'CRM & Pipeline Sales V8',
                'acosys_version' => 'v8',
                'description' => 'Kelola lead pelanggan, aktivitas sales, deal pipeline, dan jadwal kunjungan (visit plan) Acosys V8 secara online.',
                'icon' => 'target',
                'route' => 'modules.crm.index',
                'price_info' => 'Rp 119.000 / bln',
                'sort_order' => 12,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pos_online_payment_v8',
                'name' => 'Payment Gateway & PPOB V8',
                'acosys_version' => 'v8',
                'description' => 'Integrasi pembayaran digital, QRIS, dan top-up PPOB langsung terhubung dengan transaksi kasir Acosys V8.',
                'icon' => 'credit-card',
                'route' => 'modules.online-payments.index',
                'price_info' => 'Rp 89.000 / bln',
                'sort_order' => 13,
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
