# ATURAN DESAIN DAN UI (DESIGN SYSTEM RULES)

1. **Dilarang Menggunakan Emoticon atau Emoji:**
   - Dilarang keras menggunakan emoticon/emoji dalam bentuk apa pun di seluruh antarmuka aplikasi web (Blade view, modal, button, header, title, card, badge, pesan alert/toast, teks form, maupun placeholder).
   - Pesan sistem, notifikasi, teks log, dan pesan interaksi bot (termasuk tombol keyboard dan teks balasan Bot Telegram maupun WhatsApp) juga **DILARANG MENGGUNAKAN EMOJI/EMOTICON**.
   - Semua indikator visual wajib menggunakan **Icon SVG murni** (melalui komponen `<x-icon name="..." />`) atau teks bersih profesional.

2. **Flat Design Murni:**
   - Tidak boleh menggunakan efek gradasi (*gradients*). Tampilan harus bersih, solid, kontras tinggi, elegan, dan profesional.

3. **Tombol Hanya Icon:**
   - Semua tombol aksi (di tabel, header halaman, modal, form tindakan) **HANYA BERISI ICON SVG TANPA TEKS**.
   - Setiap tombol **WAJIB** memiliki atribut `data-tooltip="..."` untuk memberi tahu fungsi tombol saat di-hover pengguna.

4. **Palet Warna Utama:**
   - Primary: `#2F5BEA`
   - Dark Slate Text: `#0F172A`, `#1E293B`, `#64748B`
   - Success: `#16A34A` (Background: `#DCFCE7`)
   - Warning: `#D97706` (Background: `#FEF3C7`)
   - Danger: `#DC2626` (Background: `#FEE2E2`)
   - Border Subtle: `#E2E8F0`
   - Background Surface: `#FFFFFF`, App BG: `#F8FAFC`
