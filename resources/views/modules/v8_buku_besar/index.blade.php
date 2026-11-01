<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Cek Buku Besar (Bot Tele)
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Monitoring saldo buku besar kasir (akun paymenttype) secara realtime langsung dari database Acosys V8 via Bot Telegram.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.v8-buku-besar.index') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Data">
                <x-icon name="refresh" />
            </a>
            <a href="{{ route('settings.bot-tele') }}" class="btn-icon btn-primary" data-tooltip="Konfigurasi Gateway Bot Telegram">
                <x-icon name="settings" />
            </a>
        </div>
    </div>

    <!-- Alert jika koneksi Acosys V8 bermasalah -->
    @if($connectionError)
        <div class="alert alert-danger">
            <div>
                <strong>Peringatan Koneksi Acosys V8:</strong> {{ $connectionError }}<br>
                <small>Pastikan koneksi database Acosys V8 Anda aktif di menu Pengaturan Koneksi.</small>
            </div>
            <a href="{{ route('settings.connections') }}" class="btn-icon btn-sm btn-neutral" data-tooltip="Periksa Kredensial Database">
                <x-icon name="database" />
            </a>
        </div>
    @endif

    <!-- Status Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Gateway Bot Telegram</div>
            <div style="font-size: 20px; font-weight: 700; color: {{ $setting?->last_test_ok ? 'var(--success)' : 'var(--text-muted)' }}; display: flex; align-items: center; gap: 8px;">
                @if($setting?->last_test_ok && $setting?->bot_username)
                    <span>@<span style="color: #6D28D9;">{{ $setting->bot_username }}</span></span>
                    <span class="badge badge-success" style="font-size: 10px;">Aktif</span>
                @else
                    <span>Belum Terhubung</span>
                    <span class="badge badge-warning" style="font-size: 10px;">Perlu Token</span>
                @endif
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                @if($setting?->bot_username)
                    Kasir mengakses menu saldo melalui bot ini di Telegram.
                @else
                    Atur token bot di menu <a href="{{ route('settings.bot-tele') }}" style="color: var(--primary); text-decoration: underline;">Konfigurasi &rarr; Bot Tele V8</a>.
                @endif
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Database Acosys V8</div>
            <div style="font-size: 20px; font-weight: 700; color: var(--text-main);">
                {{ $v8Connection?->label ?: 'Belum Ada' }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                DB: <code>{{ $v8Connection?->database ?: '-' }}</code> ({{ $v8Connection?->host ?: '-' }})
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Akun Pembayaran Kasir</div>
            <div style="font-size: 24px; font-weight: 700; color: #6D28D9;">
                {{ count($paymentAccounts) }} Akun Terhubung
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Dari tabel <code>paymenttype</code> Acosys V8.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Total Saldo Kas & Bank</div>
            <div style="font-size: 22px; font-weight: 700; color: {{ $totalSaldoKasBank >= 0 ? '#16A34A' : '#DC2626' }};">
                Rp {{ number_format($totalSaldoKasBank, 0, ',', '.') }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Akumulasi seluruh saldo debit/kredit journaltrans.
            </div>
        </div>
    </div>

    <!-- Banner Panduan Modul Bot -->
    <div style="background: #F8FAFC; border: 1px solid var(--border-subtle); border-left: 4px solid #6D28D9; padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px;">
        <div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
                Integrasi Bot Telegram: Tombol [ 💰 Cek Saldo Buku Besar ] Aktif
            </div>
            <div style="font-size: 12px; color: var(--text-muted); line-height: 1.5;">
                Dengan lisensi modul ini aktif pada akun toko Anda, staf dan kasir yang login via Telegram dapat menekan tombol menu <code>💰 Cek Saldo Buku Besar</code> untuk memeriksa saldo akun kas dan bank secara instan.
            </div>
        </div>
        @if($setting?->bot_username)
            <a href="https://t.me/{{ $setting->bot_username }}" target="_blank" class="btn-icon btn-neutral" data-tooltip="Buka Bot @{{ $setting->bot_username }} di Telegram">
                <x-icon name="send" />
            </a>
        @else
            <a href="{{ route('settings.bot-tele') }}" class="btn-icon btn-neutral" data-tooltip="Setup Bot Telegram">
                <x-icon name="settings" />
            </a>
        @endif
    </div>

    <!-- Tabel Akun Buku Besar Paymenttype -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Daftar Akun Buku Besar Kasir (Tabel paymenttype)</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Hanya akun-akun pembayaran berikut yang dapat diperiksa saldonya oleh kasir via bot Telegram.
                </div>
            </div>
            <span class="badge badge-v8">{{ count($paymentAccounts) }} Akun Aktif</span>
        </div>

        @if(empty($paymentAccounts))
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <x-icon name="database" :size="36" style="margin-bottom: 12px; color: var(--text-subtle);" /><br>
                Tidak ditemukan data akun pembayaran di tabel <code>paymenttype</code> database Acosys V8 Anda.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kode Akun (paymentacc)</th>
                            <th>Nama Akun Buku Besar</th>
                            <th>Metode Pembayaran Kasir</th>
                            <th style="text-align: right;">Saldo Realtime (journaltrans)</th>
                            <th style="text-align: center;">Status Menu Bot</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentAccounts as $acc)
                            <tr>
                                <td><code>{{ $acc['account_id'] }}</code></td>
                                <td><strong>{{ $acc['account_name'] }}</strong></td>
                                <td>
                                    <span class="badge badge-neutral">{{ $acc['payment_types'] }}</span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: {{ $acc['saldo'] >= 0 ? '#16A34A' : '#DC2626' }};">
                                    {{ $acc['formatted_saldo'] }}
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-success">Tersedia di Menu Bot</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
