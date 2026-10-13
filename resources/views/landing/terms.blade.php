<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ketentuan Layanan — modsysv4</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <style>
        .content-box {
            max-width: 800px;
            margin: 40px auto;
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 40px;
            line-height: 1.7;
        }
        h1 { font-size: 24px; margin-bottom: 20px; color: var(--text-main); }
        h2 { font-size: 16px; margin-top: 24px; margin-bottom: 8px; color: var(--text-main); }
        p, li { color: var(--text-muted); font-size: 14px; margin-bottom: 12px; }
    </style>
</head>
<body style="background: var(--bg-page); padding: 20px;">
    <div style="max-width: 800px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
        <a href="{{ url('/') }}">
            <img src="{{ asset('brand/logo.svg') }}" alt="modsysv4" height="30">
        </a>
        <a href="{{ url('/') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Beranda">
            <x-icon name="arrow-left" />
        </a>
    </div>

    <div class="content-box">
        <h1>Ketentuan Layanan (Terms of Service)</h1>
        <p>Terakhir diperbarui: 13 Oktober 2026</p>

        <h2>1. Penerimaan Ketentuan</h2>
        <p>Dengan mendaftar dan menggunakan layanan <strong>modsysv4</strong>, Anda setuju untuk terikat oleh syarat dan ketentuan penggunaan ini.</p>

        <h2>2. Penggunaan Modul dan Lisensi</h2>
        <p>Modul-modul di dalam modsysv4 (termasuk modul Pengingat Piutang via WhatsApp) diaktifkan secara periodik sesuai paket langganan yang disetujui. Pengguna dilarang menyalahgunakan gateway WhatsApp untuk aktivitas spam, penipuan, atau pelanggaran hukum di Indonesia.</p>

        <h2>3. Tanggung Jawab Data</h2>
        <p>Pengguna bertanggung jawab penuh atas kebenaran kredensial database Acosys yang dihubungkan ke sistem modsysv4 serta izin pengiriman pesan kepada pelanggan masing-masing.</p>

        <h2>4. Batasan Tanggung Jawab</h2>
        <p>modsysv4 tidak bertanggung jawab atas pemblokiran nomor WhatsApp oleh pihak Meta apabila pengguna melakukan pengiriman pesan secara massal di luar ketentuan wajar WhatsApp.</p>
    </div>
</body>
</html>
