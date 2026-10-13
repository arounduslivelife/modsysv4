# TROUBLESHOOTING & CATATAN PENGEMBANGAN MODSYSV4

Dokumen ini mencatat masalah teknis umum (*gotchas*), solusi yang telah diverifikasi, dan panduan pemeliharaan sistem.

---

## 1. Masalah Versi PHP & Composer Platform
- **Kondisi:**
  - CLI lokal di Windows bisa menggunakan versi PHP yang lebih tinggi (PHP 8.5.x).
  - Web server Apache lokal berjalan di PHP 8.3.31.
- **Solusi Permanen di `composer.json`:**
  ```json
  "config": {
      "platform": {
          "php": "8.3.31"
      },
      "platform-check": false
  }
  ```
  Menjaga Symfony komponen di versi 7.4.x agar kompatibel dengan PHP 8.3 Apache tanpa memicu syntax error.

---

## 2. WA Gateway (Node.js & Baileys di VPS aaPanel)

### A. Port 3000 Sedang Digunakan (EADDRINUSE)
Jika proses node lama tersangkut saat restart manual di terminal:
```bash
fuser -k 3000/tcp
node server.js
```

### B. Kode Disconnect 515 (`Stream Errored: restart required`)
- **Penyebab:** Ini adalah respons resmi Baileys setelah scan QR berhasil di smartphone.
- **Solusi:** Sesi lama di memory map harus dihapus (`sessions.delete(sessionKey)`), lalu dibuka koneksi baru dalam 1 detik (`initSession(sessionKey, false)`). Socket baru akan langsung membaca `creds.json` yang baru disimpan dan berstatus `connected`.

### C. Masalah IPv6 di Linux VPS (`WebSocket Error ()`)
- **Penyebab:** Server VPS mencoba menghubungi `web.whatsapp.com` menggunakan IPv6 yang `Network is unreachable`.
- **Solusi Bersih:** Tambahkan satu baris di `/etc/hosts` VPS:
  ```bash
  echo "157.240.13.54 web.whatsapp.com" >> /etc/hosts
  ```

---

## 3. Komunikasi Laravel ke WA Gateway (cURL SSL di Lokal Windows)
- **Kondisi:** Panggilan HTTPS dari Laravel di Windows lokal ke `https://appwa.agungds.web.id` terkadang terkena `cURL error 60: SSL certificate problem`.
- **Solusi:** `WaGatewayClient.php` menggunakan `Http::withoutVerifying()` agar request REST API ke gateway internal selalu berhasil tanpa kendala root CA Windows.

---

## 4. Multi-Tenancy & Session Management
- **Prinsip Utama:** Jangan pernah menyimpan instance model Eloquent mentah ke dalam Session (`session(['account' => $account])`), karena deserialisasi session dapat mengubah objek menjadi array dan memicu fatal error:
  `Call to a member function connections() on array`.
- **Standar Resmi:** Hanya simpan ID di session:
  ```php
  session(['active_account_id' => $account->id]);
  ```
  Dan ambil model secara segar melalui:
  ```php
  Auth::user()->getActiveAccount();
  ```

---

## 5. Deployment ke VPS aaPanel & Error 500

### A. Git Dubious Ownership Saat Pull / Init
- **Penyebab:** Direktori dibuat oleh user `www` (aaPanel) sementara Git dijalankan sebagai `root`.
- **Solusi:**
  ```bash
  git config --global --add safe.directory /www/wwwroot/modsysv4.agungds.web.id
  ```

### B. HTTP ERROR 500 Setelah Deploy
- **Penyebab 1: Izin Tulis User Web:** Web server Apache berjalan sebagai user `www`, sehingga gagal menulis ke folder cache.
  ```bash
  chown -R www:www /www/wwwroot/modsysv4.agungds.web.id
  chmod -R 775 storage bootstrap/cache
  ```
- **Penyebab 2: Running Directory di aaPanel:** Default aaPanel mengarah ke root. Wajib diubah ke `/public` di menu **Site &rarr; Directory &rarr; Running directory: `/public`**.
- **Penyebab 3: File `.env` & `APP_KEY`:** Pastikan `APP_KEY` sama persis dengan lokal agar tidak memicu error decrypt database credentials.

---

## 6. Google OAuth: `Error 400: redirect_uri_mismatch`
- **Penyebab:** Google menolak permintaan login karena URL redirect domain VPS belum didaftarkan di Google Cloud Console.
- **Solusi:**
  1. Masuk ke [Google Cloud Console Credentials](https://console.cloud.google.com/apis/credentials).
  2. Buka Client ID Anda.
  3. Pada **Authorized redirect URIs**, tambahkan:
     ```text
     https://modsysv4.agungds.web.id/auth/google/callback
     ```
  4. Pada **Authorized JavaScript origins**, tambahkan:
     ```text
     https://modsysv4.agungds.web.id
     ```
  5. Pastikan `.env` server memiliki:
     ```env
     GOOGLE_REDIRECT_URI=https://modsysv4.agungds.web.id/auth/google/callback
     ```
  6. Jalankan `php artisan config:clear`.

