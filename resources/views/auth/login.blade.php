<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — modsysv4</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <style>
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--bg-page);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 36px 32px;
            text-align: center;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px;
            background: #FFFFFF;
            border: 1px solid var(--border-strong);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            margin-top: 24px;
        }
        .btn-google:hover {
            background: var(--bg-page);
            border-color: var(--text-main);
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <a href="{{ url('/') }}">
                <img src="{{ asset('brand/logo.svg') }}" alt="modsysv4" height="40">
            </a>

            <h2 style="font-size: 18px; font-weight: 700; margin-top: 24px; color: var(--text-main);">Selamat Datang</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;">Masuk ke akun modsysv4 Anda untuk mengelola modul dan koneksi Acosys.</p>

            @if(session('error'))
                <div class="alert alert-danger" style="margin-top: 20px; text-align: left;">
                    {{ session('error') }}
                </div>
            @endif

            <a href="{{ route('auth.google') }}" class="btn-google">
                <svg width="20" height="20" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                <span>Masuk dengan Google</span>
            </a>

            @if(config('app.env') === 'local')
                <div style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed var(--border-subtle);">
                    <small style="color: var(--text-muted); display: block; margin-bottom: 8px;">Mode Pengujian Lokal:</small>
                    <a href="{{ route('auth.dev-login') }}" style="font-size: 12px; color: var(--primary); font-weight: 600;">
                        Masuk Cepat Sebagai Admin (aroundus.livelife@gmail.com)
                    </a>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
