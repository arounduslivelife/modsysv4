<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Jurnal Tele
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Input dan monitoring transaksi jurnal kas & bank langsung dari obrolan Bot Telegram terhubung database Acosys V8.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.v8-jurnal-tele.index') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Data">
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
                    Kasir menginput jurnal melalui bot @<span style="color: #6D28D9;">{{ $setting->bot_username }}</span> di Telegram.
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
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Format Prefix Voucher</div>
            <div style="font-size: 20px; font-weight: 700; color: #6D28D9;">
                {{ $divisions[0]['description'] ?? 'STORE' }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Pola: <code>{{ $divisions[0]['frmjournalentryid'] ?? 'I/JT-######' }}</code> (No: {{ $divisions[0]['journalentryidno'] ?? '-' }})
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Jurnal Hari Ini</div>
            <div style="font-size: 22px; font-weight: 700; color: var(--primary);">
                {{ $todayCount }} Transaksi
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Total: Rp {{ number_format($todayTotal, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Banner Panduan Modul Bot -->
    <div style="background: #F8FAFC; border: 1px solid var(--border-subtle); border-left: 4px solid #6D28D9; padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; gap: 16px;">
        <div>
            <div style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">
                Integrasi Bot Telegram: Tombol [ Jurnal Tele ] Aktif
            </div>
            <div style="font-size: 12px; color: var(--text-muted); line-height: 1.5;">
                Staf atau kasir yang login via Telegram dapat menekan tombol menu <code>Jurnal Tele</code> untuk menginput jurnal transaksi kas keluar/masuk. Nomor voucher dibuat otomatis sesuai counter divisi Acosys V8.
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

    <!-- Tabel Riwayat Transaksi Jurnal -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Riwayat Jurnal Transaksi (Tabel journal & journaltrans)</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Daftar jurnal transaksi yang tersimpan di database Acosys V8.
                </div>
            </div>
            <span class="badge badge-v8">{{ count($recentJournals) }} Transaksi Terakhir</span>
        </div>

        @if(empty($recentJournals))
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <x-icon name="database" :size="36" style="margin-bottom: 12px; color: var(--text-subtle);" /><br>
                Belum ada catatan jurnal pada database Acosys V8 Anda.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Voucher</th>
                            <th>Tanggal & Jam</th>
                            <th>Keterangan / Memo</th>
                            <th>Akun Debit</th>
                            <th>Akun Kredit</th>
                            <th style="text-align: right;">Total Nilai</th>
                            <th>Kasir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentJournals as $j)
                            <tr>
                                <td><code>{{ $j['jtid'] }}</code></td>
                                <td style="white-space: nowrap;">
                                    <strong>{{ \Carbon\Carbon::parse($j['jtdate'])->format('d/m/Y') }}</strong>
                                    <span style="font-size: 11px; color: var(--text-muted); margin-left: 4px;">{{ $j['jttime'] }}</span>
                                </td>
                                <td>{{ $j['memo'] }}</td>
                                <td><span style="font-size: 12px; color: var(--text-main);">{{ $j['debit_summary'] }}</span></td>
                                <td><span style="font-size: 12px; color: var(--text-main);">{{ $j['credit_summary'] }}</span></td>
                                <td style="text-align: right; font-weight: 700; color: #16A34A; white-space: nowrap;">
                                    {{ $j['formatted_value'] }}
                                </td>
                                <td>
                                    <span class="badge badge-neutral">{{ $j['usercreate'] ?: 'admin' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
