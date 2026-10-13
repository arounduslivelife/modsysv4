<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>modsysv4 — Platform Ekosistem Modul SaaS untuk Acosys V4</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <style>
        .landing-nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .hero {
            max-width: 900px;
            margin: 60px auto 40px auto;
            text-align: center;
            padding: 0 20px;
        }
        .hero h1 {
            font-size: 44px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: var(--text-main);
            margin-bottom: 18px;
        }
        .hero p {
            font-size: 18px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .btn-google-login {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #FFFFFF;
            color: #0F172A;
            border: 1px solid var(--border-strong);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: border-color 0.15s, background-color 0.15s;
        }
        .btn-google-login:hover {
            border-color: var(--text-main);
            background: #F8FAFC;
        }
        .features-grid {
            max-width: 1100px;
            margin: 60px auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 0 20px;
        }
        .feature-box {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 24px;
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .feature-box h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .feature-box p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }
        .footer {
            border-top: 1px solid var(--border-subtle);
            padding: 30px 20px;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 80px;
        }
        .footer a {
            color: var(--text-main);
            font-weight: 500;
            margin: 0 10px;
        }
    </style>
</head>
<body style="background: var(--bg-page);">
    <nav class="landing-nav">
        <a href="{{ url('/') }}">
            <img src="{{ asset('brand/logo.svg') }}" alt="modsysv4" height="34">
        </a>
        <div>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-icon btn-primary" data-tooltip="Buka Dashboard">
                    <x-icon name="arrow-left" style="transform: rotate(180deg);" />
                </a>
            @else
                <a href="{{ route('auth.google') }}" class="btn-icon btn-primary" data-tooltip="Masuk dengan Google">
                    <x-icon name="log-out" style="transform: rotate(180deg);" />
                </a>
            @endauth
        </div>
    </nav>

    <header class="hero">
        <h1>Transformasi Acosys V4 ke Ekosistem Modul Cloud</h1>
        <p>Hubungkan database Acosys desktop Anda dengan mudah. Aktifkan modul sesuai kebutuhan: Pengingat Piutang via WhatsApp Gateway, Katalog Web Mandiri, dan Analitik Pemilik Bisnis.</p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn-icon btn-primary" style="width: 54px; height: 54px;" data-tooltip="Buka Dashboard Aplikasi">
                <x-icon name="arrow-left" :size="24" style="transform: rotate(180deg);" />
            </a>
        @else
            <a href="{{ route('auth.google') }}" class="btn-google-login">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Masuk dengan Akun Google</span>
            </a>

            @if(config('app.env') === 'local')
                <div style="margin-top: 16px;">
                    <a href="{{ route('auth.dev-login') }}" style="font-size: 12px; color: var(--primary); text-decoration: underline;">
                        Mode Cepat Pengembang (Dev Login sebagai Admin)
                    </a>
                </div>
            @endif
        @endauth
    </header>

    <section class="features-grid">
        <div class="feature-box">
            <div class="feature-icon">
                <x-icon name="bell-ring" :size="24" />
            </div>
            <h3>Pengingat Piutang WA</h3>
            <p>Otomatisasi pengiriman pesan jatuh tempo faktur piutang ke konsumen atau nomor rekap owner menggunakan nomor sender WhatsApp toko Anda sendiri.</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">
                <x-icon name="database" :size="24" />
            </div>
            <h3>Konektivitas Fleksibel</h3>
            <p>Dukungan koneksi database desktop melalui ZeroTier, Tailscale, maupun IP publik dengan enkripsi kredensial ganda (AES-256).</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">
                <x-icon name="users-round" :size="24" />
            </div>
            <h3>Multi-Toko & Multi-Staf</h3>
            <p>Kelola banyak koneksi cabang Acosys dalam satu akun usaha, undang staf toko, dan atur hak akses modul secara terpusat.</p>
        </div>

        <div class="feature-box">
            <div class="feature-icon">
                <x-icon name="shield" :size="24" />
            </div>
            <h3>Lisensi Modular</h3>
            <p>Menu yang belum dibeli tampil elegan dan dapat diaktivasi kapan saja secara mandiri melalui persetujuan admin modsysv4.</p>
        </div>
    </section>

    <footer class="footer">
        <div>&copy; {{ date('Y') }} <strong>modsysv4</strong> — Ekosistem Modul SaaS Acosys.</div>
        <div style="margin-top: 8px;">
            <a href="{{ route('landing.privacy') }}">Kebijakan Privasi</a> ·
            <a href="{{ route('landing.terms') }}">Ketentuan Layanan</a>
        </div>
    </footer>
</body>
</html>
