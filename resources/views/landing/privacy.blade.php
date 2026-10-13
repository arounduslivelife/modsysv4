<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi — modsysv4</title>
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
        <h1>Kebijakan Privasi (Privacy Policy)</h1>
        <p>Terakhir diperbarui: 13 Oktober 2026</p>

        <h2>1. Informasi yang Kami Kumpulkan</h2>
        <p>Aplikasi <strong>modsysv4</strong> mengumpulkan informasi akun dasar melalui Google Sign-In, meliputi nama, alamat email, dan foto profil Anda. Kami tidak mengakses kata sandi akun Google Anda.</p>

        <h2>2. Data Kredensial Database Acosys</h2>
        <p>Kredensial database Acosys (host, port, database, user, password) yang Anda masukkan disimpan secara terenkripsi (AES-256) dan hanya digunakan untuk menghubungkan modul SaaS pilihan Anda ke database kasir/akuntansi Anda secara langsung.</p>

        <h2>3. Penggunaan WhatsApp Gateway</h2>
        <p>Data pesan WhatsApp dikirimkan langsung melalui nomor pengirim (sender) yang Anda tautkan sendiri ke sistem gateway. Kami tidak menjual atau membagikan riwayat pesan maupun nomor kontak konsumen Anda kepada pihak ketiga.</p>

        <h2>4. Keamanan Data</h2>
        <p>Kami menerapkan standar keamanan enkripsi berlapis dan tidak menyimpan salinan data transaksi Acosys di server SaaS kecuali ringkasan sementara yang diperlukan untuk operasional modul.</p>

        <h2>5. Kontak Kami</h2>
        <p>Untuk pertanyaan mengenai kebijakan privasi ini, silakan hubungi kami melalui email: <strong>aroundus.livelife@gmail.com</strong>.</p>
    </div>
</body>
</html>
