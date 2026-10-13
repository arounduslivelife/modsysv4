# WhatsApp Gateway Multi-Session (modsysv4)

Gateway WhatsApp berbasis Node.js & Baileys yang mendukung multi-device / multi-session dengan penyimpanan session otomatis.

---

## 🚀 Panduan Deploy di aaPanel (`appwa.agungds.web.id`)

### 1. Buat Website Node Project di aaPanel
1. Masuk ke **aaPanel** -> menu **Website** -> tab **Node project** -> klik **Add Node project**.
2. Masukkan pengaturan:
   - **Path**: `/www/wwwroot/appwa.agungds.web.id`
   - **Node version**: Node v18 atau v20+ (install via aaPanel Version Manager bila belum ada).
   - **Run opt**: `npm run start` (atau script `server.js`).
   - **Port**: `3000` (atau port bebas pilihan Anda).
   - **Name**: `modsysv4-wa-gateway`.
3. Di tab **Domain**, masukkan `appwa.agungds.web.id`.
4. Pasang **SSL (Let's Encrypt)** untuk domain `appwa.agungds.web.id`.

### 2. Upload / Paste Folder
Upload seluruh isi folder `wa-gateway` ini (beserta `package.json`, `server.js`, dan `.env`) ke `/www/wwwroot/appwa.agungds.web.id`.

### 3. Konfigurasi `.env` di Server
Buka file `.env` di `/www/wwwroot/appwa.agungds.web.id/.env`:
```env
PORT=3000
API_KEY=modsysv4_secret_key_2026
SESSIONS_DIR=./sessions
```
*(Pastikan API_KEY sama dengan nilai `WA_GATEWAY_KEY` di `.env` Laravel modsysv4).*

### 4. Install Modules & Jalankan
Di terminal aaPanel atau via tombol **Install dependencies (npm install)** di aaPanel, jalankan `npm install`, lalu klik **Start project**.

---

## 🧪 Menguji Gateway Lokal (di Laptop Anda)

Untuk menjalankan gateway ini di komputer lokal:
```powershell
cmd.exe /c "set PATH=C:\Program Files\nodejs;%PATH% && node.exe server.js"
```
Tes kesehatan di browser:
`http://localhost:3000/health`
