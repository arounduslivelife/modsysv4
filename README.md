# modsysv4 &mdash; Cloud SaaS Modular Platform untuk Acosys V4

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11" />
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+" />
  <img src="https://img.shields.io/badge/Node.js-Baileys%20v6-339933?style=for-the-badge&logo=nodedotjs&logoColor=white" alt="Node.js Baileys" />
  <img src="https://img.shields.io/badge/Database-MariaDB%20%2F%20MySQL-003545?style=for-the-badge&logo=mariadb&logoColor=white" alt="MariaDB" />
  <img src="https://img.shields.io/badge/Security-AES--256--CBC-0A84FF?style=for-the-badge&logo=auth0&logoColor=white" alt="AES-256" />
</p>

---

## 📌 Tentang modsysv4

**modsysv4** adalah platform SaaS (*Software as a Service*) modern yang dirancang untuk memperluas kapabilitas software desktop **Acosys V4 ERP** ke dalam ekosistem cloud. Tanpa mengubah database inti Acosys di komputer kasir toko, modsysv4 menghubungkan data penjualan dan keuangan dengan layanan cloud otomasi seperti WhatsApp Gateway, laporan owner, dan katalog digital.

Aplikasi mengusung arsitektur **Multi-Tenant (Per-Akun Usaha)** dengan model **Lisensi Modular**: toko hanya membayar modul yang mereka butuhkan, dan satu lisensi toko dapat digunakan bersama oleh seluruh staf kasir/penagihan tanpa biaya tambahan per pengguna (*no per-seat fee*).

---

## 🚀 Fitur Utama

### 1. 🔔 Modul Pengingat Piutang WhatsApp (Debt Reminder)
- **Sinkronisasi Piutang Real-Time:** Membaca data nota belum lunas dan jatuh tempo langsung dari basis data Acosys toko (mendukung IP Publik, Cloud Server, maupun VPN).
- **Kirim Notifikasi Sekali Klik:** Mengirimkan pesan tagihan resmi instan langsung ke nomor WhatsApp konsumen.
- **Otomasi Harian Terjadwal (Cron Scheduler):** Pengiriman otomatis setiap pagi ke pelanggan yang jatuh tempo sesuai jam kirim yang ditentukan toko.
- **Mode Penerima Fleksibel:**
  1. *Hanya Pelanggan:* Tagihan personal ke masing-masing debitur.
  2. *Hanya Nomor Tetap (Owner):* Rekap ringkasan total piutang overdue ke WhatsApp pemilik/bagian keuangan.
  3. *Keduanya:* Mengingatkan pelanggan sekaligus melaporkan rekap harian ke pemilik toko.
- **Anti-Spam & Keamanan Nomor:** Dilengkapi pembatas cerdas 1x per hari per pelanggan dan jeda aman (*rate-limiting*) antar pesan untuk menjaga nomor WhatsApp toko dari risiko pemblokiran.

### 2. 📱 Kustomisasi Template Pesan & Live Smartphone Mockup
- **Editor Template Mandiri:** Pengguna bebas menyusun gaya bahasa dan redaksi pesan penagihan pelanggan maupun laporan rekap owner.
- **Chip Variabel Sekali Klik:** Menyisipkan tag dinamis seperti `{nama_pelanggan}`, `{nama_toko}`, `{total_piutang}`, `{jumlah_nota}`, `{tanggal_tempo}`, dan `{daftar_pelanggan}` langsung pada kursor.
- **Simulasi WhatsApp Realistis (Smartphone Frame):**
  - Desain bodi titanium dengan *dynamic island*, status bar (jam, baterai, wifi), dan home indicator.
  - Tampilan antarmuka WhatsApp asli (header hijau khas, gelembung chat hijau `#D9FDD3`, ekor pesan, dan **centang dua biru - dibaca**).
  - **Live Markdown Parser:** Otomatis mengubah tanda `*teks tebal*` dan `_teks miring_` secara *real-time* seperti pada aplikasi WhatsApp sesungguhnya.
- **Tombol Reset:** Kembalikan ke format baku sistem kapan saja dengan satu klik.

### 3. 🌐 WhatsApp Gateway Microservice (Baileys Node.js)
- Engine mandiri berbasis WebSocket Baileys Multi-Device.
- Tautkan nomor WhatsApp toko hanya dengan memindai kode QR dari browser.
- Sesi login aman dan persisten, berjalan stabil di VPS Linux (aaPanel / PM2 / Systemd).
- Rest API terproteksi API Key (`X-API-Key`).

### 4. 🏢 Arsitektur Tenancy & Manajemen Lisensi
- **Google OAuth Login:** Autentikasi aman tanpa perlu mengingat kata sandi baru.
- **Multi-Tenant Usaha:** Setiap akun toko memiliki isolasi data, nomor pengirim, dan konfigurasi database tersendiri.
- **Katalog Modul Modular:**
  - *Pengingat Piutang WA*
  - *Katalog Web & Self-Order Pelanggan*
  - *Dashboard Analitik Owner*
  - *Absensi & Komisi Sales Lapangan*
- **Panel Administrator SaaS:**
  - Kelola status aktivasi per modul untuk setiap akun pelanggan.
  - Preset durasi langganan instan (`+30 Hari`, `+90 Hari`, `+1 Tahun`, `Permanen`).
  - Pencatatan nomor invoice dan riwayat transaksi langganan.

### 5. 🛡️ Keamanan & Enkripsi Data
- Seluruh kredensial database toko (Host, Port, User, Password) dienkripsi menggunakan standar militer **AES-256-CBC** (`Crypt::encryptString`) dengan kunci unik aplikasi.

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|---|---|
| **Backend Utama** | Laravel 11.x, PHP 8.2+, Eloquent ORM, Task Scheduler |
| **Frontend UI** | Blade Component System, Pure Flat Design System, Lucide Icons |
| **Tipografi & Gaya** | Plus Jakarta Sans, JetBrains Mono, Vanilla CSS & Vanilla JavaScript |
| **Gateway WhatsApp** | Node.js, Express.js, `@whiskeysockets/baileys` v6 |
| **Basis Data** | MariaDB 10.x / MySQL 8.x |
| **Autentikasi** | Laravel Socialite (Google OAuth 2.0) |

---

## 📂 Struktur Direktori Proyek

```
modsysv4/
├── app/
│   ├── Console/Commands/       # Cron pengingat otomatis (SendPengingatPiutangCommand)
│   ├── Http/Controllers/       # Controller modul & tenant (PengingatPiutang, Admin, dll.)
│   ├── Models/                 # Model database Eloquent (User, Account, Module, dll.)
│   └── Services/               # Layanan integrasi (AcosysService, WhatsAppGatewayService)
├── database/migrations/        # Migrasi 13 tabel SaaS multi-tenant
├── public/
│   └── panduan.html            # Panduan lengkap tata cara penggunaan (Sudut pandang user)
├── resources/views/
│   ├── components/             # Reusable UI components & icon blade
│   ├── modules/                # Tampilan modul (Pengingat Piutang, Settings, Rekap)
│   └── admin/                  # Tampilan dashboard administrator SaaS
├── routes/
│   └── web.php                 # Rute aplikasi dan kontrol akses
├── wa-gateway/                 # Microservice Node.js Baileys WhatsApp Gateway
│   ├── server.js               # Express API & Baileys socket handler
│   └── package.json            # Dependensi Baileys gateway
└── .agents/                    # Dokumentasi arsitektur, panduan teknis, dan catatan dev
```

---

## ⚡ Panduan Instalasi Lokal / Server

### 1. Prasyarat
- PHP >= 8.2 (ekstensi: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo`)
- Composer >= 2.x
- Node.js >= 18.x & NPM
- MySQL / MariaDB

### 2. Kloning & Pengaturan Aplikasi Web

```bash
# Clone repositori
git clone https://github.com/arounduslivelife/modsysv4.git
cd modsysv4

# Pasang dependensi PHP
composer install

# Siapkan berkas konfigurasi .env
cp .env.example .env

# Generate Application Key (Krusial untuk enkripsi AES-256)
php artisan key:generate

# Konfigurasikan koneksi basis data di file .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=acosysv4modul
DB_USERNAME=root
DB_PASSWORD=

# Jalankan migrasi dan seeder awal
php artisan migrate --seed

# Hubungkan symlink storage publik
php artisan storage:link
```

### 3. Menjalankan WhatsApp Gateway (Node.js)

```bash
cd wa-gateway
npm install
node server.js
```
*(Untuk lingkungan produksi di VPS, jalankan menggunakan PM2 atau panel Node.js aaPanel).*

### 4. Menjalankan Scheduler Otomasi Pengingat Piutang

Tambahkan baris berikut ke crontab server produksi (`crontab -e`):

```bash
* * * * * cd /path/to/modsysv4 && php artisan schedule:run >> /dev/null 2>&1
```

Atau uji pengiriman secara langsung melalui CLI:
```bash
# Jalankan pengingat otomatis harian
php artisan reminder:send-piutang

# Paksa pengiriman sekarang (bypass jam kirim)
php artisan reminder:send-piutang --force
```

---

## 📖 Panduan Pengguna Lengkap

Dokumentasi lengkap petunjuk langkah-demi-langkah bagi pemilik toko dan kasir tersedia di:  
📄 **[`public/panduan.html`](public/panduan.html)**

Panduan mencakup:
1. Cara Login via Google
2. Cara Berlangganan & Memilih Paket Modul
3. Cara Menautkan WhatsApp Toko via QR Code
4. Cara Menghubungkan Database Komputer Kasir Acosys
5. Cara Menggunakan Pengingat Piutang & Mengatur Jam Kirim
6. Cara Kustomisasi Isi Template Pesan WhatsApp & Tag Variabel

---

## 📄 Lisensi

Dikembangkan secara eksklusif untuk ekosistem **Acosys V4**. Hak Cipta &copy; 2026. Seluruh hak cipta dilindungi.
