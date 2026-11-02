# DOKUMENTASI PROYEK MODSYSV4 (SAAS ACOSY V4 MODULES)

Dokumen ini berisi rangkuman arsitektur, panduan teknis, dan catatan penting pengembangan platform **modsysv4**.

---

## 1. Ringkasan Eksekutif
- **Nama Proyek:** modsysv4
- **Tujuan:** Menghubungkan aplikasi akuntansi/kasir desktop **Acosys V4** ke modul cloud SaaS modern.
- **Modul Pertama:** Pengingat Piutang Pelanggan Otomatis via WhatsApp Gateway.
- **Repository Root:** `F:\htdocs\acosysv4modul`
- **Akses Web Lokal:** `http://localhost/acosysv4modul/` (Apache XAMPP dengan root DocumentRoot `F:/htdocs`, tanpa `/public/` di URL).

---

## 2. Arsitektur Sistem & Komponen

```
+-------------------------------------------------------------+
|                     PENGGUNA / OWNER TOKO                   |
+-------------------------------------------------------------+
                              | (Browser HTTPS)
                              v
+-------------------------------------------------------------+
|                   APLIKASI SAAS (LARAVEL)                   |
|                   F:\htdocs\acosysv4modul                   |
|  - Multi-tenancy (Akun Toko & Staf)                         |
|  - Auth Google OAuth (aroundus.livelife@gmail.com = Admin)  |
|  - Katalog & Aktivasi Modul                                 |
|  - Modul 1: Pengingat Piutang WA                            |
+-------------------------------------------------------------+
           |                                       |
           | (Koneksi DB SaaS)                     | (API HTTPS / JSON)
           v                                       v
+-------------------------------+   +-------------------------------+
|     DATABASE SAAS (MARIADB)   |   |     WA GATEWAY (NODE.JS)      |
|     Host: agungds.web.id      |   |   https://appwa.agungds.web.id|
|     Database: acosysv4modul   |   |   Port: 3000 (Baileys v6.7)   |
|   13 Tabel Inti SaaS          |   |   Multi-device Baileys Auth   |
+-------------------------------+   +-------------------------------+
                                                   |
           (Koneksi TCP MySQL Desktop)             | (WebSocket TLS)
           v                                       v
+-------------------------------+   +-------------------------------+
|     DATABASE TOKO ACOSY V4    |   |     SERVER WHATSAPP (META)    |
|   Database Kasir/Desktop Toko |   |     web.whatsapp.com:443      |
|   (IP Publik / ZeroTier)      |   |     (Notifikasi ke Pelanggan) |
+-------------------------------+   +-------------------------------+
```

---

## 3. Aturan Desain (Design System Rules)
1. **Flat Design Murni:** Tidak boleh menggunakan efek gradasi (*gradients*). Tampilan bersih, kontras tinggi, elegan, dan profesional.
2. **Tombol Hanya Icon:**
   - Semua tombol aksi (tabel, header, modal) **HANYA BERISI ICON SVG TANPA TEKS**.
   - Setiap tombol **WAJIB** memiliki atribut `data-tooltip="..."` untuk informasi pengguna.
   - Posisi tombol konsisten: Aksi halaman di kanan atas, aksi tabel di kolom kanan.
3. **DILARANG MENGGUNAKAN EMOTICON / EMOJI:**
   - Seluruh antarmuka web (UI Blade, header, tabel, card, badge, teks bantuan, placeholder, toast/alert) **TIDAK BOLEH MENGGUNAKAN EMOTICON ATAU EMOJI APAPUN** (seperti robot, tangan, kantong uang, gembok, grafik, kartu, boks, tanda centang/silang, dan icon grafis karakter lainnya).
   - Tampilan visual murni menggunakan **Icon SVG** (komponen `<x-icon name="..." />`) atau teks bersih profesional.
   - Pesan sistem dan pesan interaksi bot Telegram juga tidak boleh menggunakan emoticon/emoji; gunakan format teks bersih, tebal (`*teks*`), atau miring (`_teks_`).
4. **Palet Warna Utama:**
   - Primary: `#2F5BEA`
   - Dark Slate Text: `#0F172A`, `#1E293B`, `#64748B`
   - Success: `#16A34A` (Background: `#DCFCE7`)
   - Warning: `#D97706` (Background: `#FEF3C7`)
   - Danger: `#DC2626` (Background: `#FEE2E2`)
   - Border Subtle: `#E2E8F0`
   - Background Surface: `#FFFFFF`, App BG: `#F8FAFC`

---

## 4. Perintah Penting (CLI & Artisan)

- **Menjalankan Pengingat Piutang Otomatis:**
  ```bash
  php artisan reminder:send-piutang
  ```
- **Paksa Eksekusi Pengingat Sekarang (Bypass Jam Kirim):**
  ```bash
  php artisan reminder:send-piutang --force
  ```
- **Task Scheduler Laravel:**
  ```bash
  php artisan schedule:run
  ```
- **Cron Job Produksi (aaPanel Linux):**
  ```bash
  * * * * * cd /www/wwwroot/modsysv4.domain.com && php artisan schedule:run >> /dev/null 2>&1
  ```

---

## 5. Sistem Lisensi & Tenancy Toko

1. **Tenant-Based (Per Akun Usaha):**
   - Lisensi modul melekat pada tabel `account_modules` (relasi `account_id` dan `module_id`), **bukan per user**.
   - Satu toko yang berlangganan dapat menambahkan banyak staf di menu *Staf & Akses* tanpa biaya lisensi tambahan.
2. **Siklus Hidup (Lifecycle):**
   - **Terkunci:** `is_active = false` atau tanggal `expires_at < today()`. Sidebar merender modul redup + gembok.
   - **Aktif:** `is_active = true` dan `expires_at >= today()`. Modul terbuka penuh.
   - **Permanen:** `is_active = true` dan `expires_at = null` (tanpa batas waktu / beli putus).
3. **Pengelolaan Admin (`/admin/accounts/{id}`):**
   - Admin utama (`aroundus.livelife@gmail.com`) mengontrol status checklist aktif, tanggal mulai (`starts_at`), tanggal kedaluwarsa (`expires_at`), dan catatan invoice pembayaran.
   - Presets cepat: `+30 Hari` (bulanan), `+90 Hari` (triwulan), `+1 Tahun` (tahunan), `Permanen` (lifetime).

---

## 6. Kustomisasi Template Pesan WhatsApp

- **Tabel Basis Data:** `reminder_templates` (`account_id`, `name`, `type`, `body`).
- **Tipe Template:**
  1. `customer`: Format pesan penagihan yang dikirim langsung ke WhatsApp konsumen.
     - Variabel didukung: `{nama_pelanggan}`, `{nama_toko}`, `{total_piutang}`, `{jumlah_nota}`, `{tanggal_tempo}`.
  2. `recap`: Format laporan ringkasan piutang harian yang dikirim ke nomor tetap Owner/Finance.
     - Variabel didukung: `{nama_toko}`, `{tanggal_sekarang}`, `{total_piutang}`, `{total_pelanggan}`, `{daftar_pelanggan}`.
- **Engine Pemrosesan:**
  - `PengingatPiutangController::renderMessage($template, $data)` untuk pengiriman tunggal dari web UI.
  - `SendPengingatPiutangCommand` untuk pengiriman terjadwal via cron scheduler.
- **Fitur Antarmuka (`settings.blade.php`):**
  - Chip tombol penyisip variabel cepat (`.btn-chip`) dengan sekali klik.
  - Mockup Smartphone Titanium dengan antarmuka WhatsApp asli (Header hijau `#008069`, gelembung hijau `#D9FDD3`, ekor pesan, dan centang dua biru dibaca).
  - Parser live Markdown WhatsApp (`*teks tebal*` & `_teks miring_`).
  - Tombol reset ke template bawaan (*default*).

---

## 7. Repositori Git & Deployment VPS

- **URL GitHub:** `https://github.com/arounduslivelife/modsysv4`
- **Branch Produksi:** `main`
- **Lokasi Direktori VPS (aaPanel):** `/www/wwwroot/modsysv4.agungds.web.id`
- **Domain Produksi:** `https://modsysv4.agungds.web.id`
- **Perintah Update / Pull di VPS:**
  ```bash
  cd /www/wwwroot/modsysv4.agungds.web.id
  git pull origin main
  php artisan optimize:clear
  ```
- **Aturan aaPanel:**
  - *Running Directory:* Wajib disetel ke `/public`.
  - *URL Rewrite:* Menggunakan aturan default Apache `.htaccess` (sudah sesuai otomatis).
  - *Izin File:* `chown -R www:www ...` dan `chmod -R 775 storage bootstrap/cache`.

---

## 8. Otomasi Cron Scheduler via URL Link

Selain melalui CLI `php artisan schedule:run`, sistem menyediakan endpoint HTTP yang aman untuk dieksekusi via **aaPanel Cron (Access URL)** atau webhook eksternal:

- **Key Pengaman:** Divalidasi via parameter `?key=` atau header `X-Cron-Key` mencocokkan `CRON_SECRET` / `WA_GATEWAY_KEY` (`modsysv4_secret_key_2026`).
- **Endpoint URL:**
  1. `https://modsysv4.agungds.web.id/cron/send-piutang?key=modsysv4_secret_key_2026` (Jadwal normal)
  2. `https://modsysv4.agungds.web.id/cron/send-piutang?key=modsysv4_secret_key_2026&force=1` (Bypass jendela jam kirim untuk testing)
  3. `https://modsysv4.agungds.web.id/cron/schedule-run?key=modsysv4_secret_key_2026` (Eksekusi seluruh task scheduler Laravel)

---

## 9. Konfigurasi Google OAuth 2.0

- **Client ID:** `98250414104-4ehiqhph71h5nn6jfmkvos7sk431cmcv.apps.googleusercontent.com`
- **Authorized Redirect URIs (Google Cloud Console):**
  - Lokal: `http://localhost/acosysv4modul/auth/google/callback`
  - VPS: `https://modsysv4.agungds.web.id/auth/google/callback`
- **Authorized JavaScript Origins:**
  - Lokal: `http://localhost`
  - VPS: `https://modsysv4.agungds.web.id`
- **Konfigurasi `.env`:**
  - `GOOGLE_REDIRECT_URI=https://modsysv4.agungds.web.id/auth/google/callback`

---

## 10. Arsitektur Modul Acosys V8 & Gateway Bot Tele

Platform memisahkan antara **Infrastruktur Gateway Telegram** dan **Modul Bisnis Acosys V8 (Add-on Berbayar)**:

1. **Infrastruktur (Konfigurasi &rarr; Bot Tele V8):**
   - Rute: `/settings/bot-tele` (`settings.bot-tele`).
   - Menyimpan kredensial token Telegram bot dari `@BotFather` (terenkripsi AES-256 via `Crypt`).
   - Menghubungkan bot dengan koneksi database kasir Acosys V8 (`acosys_connections`).
   - Pendaftaran Webhook otomatis ke `https://modsysv4.domain.com/api/telegram/webhook/{secret}`.
   - Monitoring sesi pengguna Telegram yang sedang login/aktif (`bot_tele_sessions`).
   - Fitur uji kirim pesan notifikasi.

2. **Modul Bisnis V8 Modular (Katalog SaaS):**
   - **Modul 1: Cek Buku Besar (Bot Tele)** (`code: v8_buku_besar`):
     - Rute Web: `/modules/v8-buku-besar` (`modules.v8-buku-besar.index`).
     - Fitur Bot: Menampilkan pilihan akun kas/bank populer dan pengecekan saldo realtime dari tabel `journaltrans`.
     - Lisensi berbasis toko (`account_modules`), dengan harga langganan bulanan/tahunan tersendiri.
   - **Modul 2: Jurnal Tele (Bot Tele)** (`code: v8_jurnal_tele`):
     - Rute Web: `/modules/v8-jurnal-tele` (`modules.v8-jurnal-tele.index`).
     - Fitur Bot: Input transaksi jurnal akuntansi langsung dari obrolan Telegram via conversational flow 5 langkah (Catatan/Memo -> Akun Debit -> Nominal Debit -> Akun Kredit -> Nominal Kredit seimbang -> Konfirmasi & Simpan).
     - Menghasilkan nomor jurnal otomatis berbasis template formula divisi (`division.journalprefixformula`).
   - **Modul 3: Cek Stok Produk (Bot Tele)** (`code: v8_cek_stok`):
     - Rute Web: `/modules/v8-cek-stok` (`modules.v8-cek-stok.index`).
     - Fitur Bot: Pencarian nama produk, kode barang, atau barcode secara realtime. Menampilkan kartu stok fisik per divisi/gudang toko (`stockbalance`), daftar harga jual multi-satuan (`productunit`), dan status stok minimum.
   - **Modul 4: Laporan Piutang (Bot Tele)** (`code: v8_laporan_piutang`):
     - Rute Web: `/modules/v8-laporan-piutang` (`modules.v8-laporan-piutang.index`).
     - Fitur Bot: Ringkasan piutang usaha toko (`customerbalance`), fitur Top 5 Pelanggan Piutang Terbesar, pencarian cerdas pelanggan, rincian lembar faktur belum lunas (`sales`), serta pemisahan transparan antara total faktur, titipan kredit gantung, dan sisa piutang bersih riil.
   - **Modul 5: Laporan Hutang (Bot Tele)** (`code: v8_laporan_hutang`):
     - Rute Web: `/modules/v8-laporan-hutang` (`modules.v8-laporan-hutang.index`).
     - Fitur Bot: Ringkasan hutang usaha toko ke vendor/supplier (`supplierbalance`), fitur Top 5 Pemasok Hutang Terbesar, pencarian cerdas supplier, rincian faktur pembelian belum lunas (`purchase`), status jatuh tempo (overdue), dan sisa hutang bersih.

3. **Dynamic Keyboard di Bot Telegram:**
   - Saat kasir login atau mengirim perintah di Telegram, bot memeriksa lisensi aktif toko pengguna:
     - Jika toko memiliki modul aktif, tombol menu terkait otomatis muncul di Reply Keyboard Telegram (misal: `Cek Saldo Buku Besar`, `Jurnal Tele`, `Cek Stok Produk`, `Laporan Piutang`, `Laporan Hutang`, `Logout`).
     - Jika toko belum melanggan modul tersebut atau masa aktif habis, tombol tidak muncul dan akses perintah langsung diblokir dengan pemberitahuan ramah untuk aktivasi modul.

---

## 11. Arsitektur State Machine & UX Bot Telegram (BotTeleController)

Untuk menjamin pengalaman pengguna kasir yang cepat, responsif, dan bebas error stuck:

1. **Prioritas Menu Navigasi (Menu First Evaluation):**
   - Perintah dan tombol menu navigasi Reply Keyboard (`/saldo`, `/jurnal`, `/stok`, `/piutang`, `/hutang`, `/logout`) dievaluasi di urutan pertama sebelum status sesi percakapan (`$session->state`).
   - Hal ini memastikan kasir dapat berpindah menu kapan saja tanpa pernah terjebak di state input sebelumnya.

2. **Auto-Listen Otomatis (Tanpa Klik Ganda):**
   - Saat kasir menekan tombol menu `Laporan Piutang`, `Laporan Hutang`, atau `Cek Stok Produk`, bot langsung menampilkan kartu ringkasan sekaligus otomatis mengaktifkan status pendengar (`piutang_wait_customer`, `hutang_wait_supplier`, `stok_wait_keyword`).
   - Pengguna dapat langsung mengetik nama di chat tanpa harus menekan tombol pencarian tambahan.

3. **Pencarian Cerdas & Cross-Category Fallback:**
   - Mesin pencari di `AcosysConnector` mendukung multi-token matching, relevance ranking, dan fallback 3-karakter prefix.
   - Jika pengguna keliru mengetik nama pelanggan di menu Laporan Hutang (atau nama pemasok di Laporan Piutang), bot secara cerdas mendeteksi kata kunci di kategori seberang dan memberikan tombol rekomendasi instan untuk membuka rincian kartu tersebut.

4. **Omni-Search Mode Idle:**
   - Jika pengguna berada di menu utama (idle) dan langsung mengetik teks tanpa memilih modul, bot otomatis mencari ke data Pelanggan, Pemasok, dan Produk secara serentak, lalu menyajikan tombol inline pilihan sehingga bot tidak pernah diam tanpa respon.

5. **Penyajian Data Akuntansi Transparan (Net Balance vs Faktur Aktif):**
   - Acosys V8 menghitung saldo piutang dan hutang berdasarkan saldo bersih mutasi buku besar (`SUM(debit) - SUM(credit)`).
   - Jika terdapat selisih antara akumulasi faktur aktif dan saldo netto (akibat kelebihan bayar atau retur lama yang belum di-offset ke faktur baru), kartu rincian bot menyajikan:
     - Total Faktur Belum Lunas (Gross)
     - Titipan / Kredit Gantung
     - Total Piutang/Hutang Bersih (Net)



