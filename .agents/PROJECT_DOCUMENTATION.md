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
3. **Palet Warna Utama:**
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


