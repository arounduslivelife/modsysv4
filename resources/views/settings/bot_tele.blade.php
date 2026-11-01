<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Bot Tele V8
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Konfigurasi gateway Bot Telegram dan manajemen sesi kasir terhubung ke database Acosys V8.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('settings.bot-tele') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Halaman">
                <x-icon name="refresh" />
            </a>
            <button type="submit" form="botTeleForm" class="btn-icon btn-primary" data-tooltip="Simpan Konfigurasi Bot">
                <x-icon name="save" />
            </button>
        </div>
    </div>

    <!-- Status Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Status Bot Telegram</div>
            <div style="font-size: 20px; font-weight: 700; color: {{ $setting->last_test_ok ? 'var(--success)' : 'var(--text-muted)' }}; display: flex; align-items: center; gap: 8px;">
                @if($setting->last_test_ok && $setting->bot_username)
                    <span>@<span style="color: #6D28D9;">{{ $setting->bot_username }}</span></span>
                    <span class="badge badge-success" style="font-size: 10px;">Aktif</span>
                @else
                    <span>Belum Terhubung</span>
                    <span class="badge badge-warning" style="font-size: 10px;">Perlu Token</span>
                @endif
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                {{ $setting->last_test_message ?: 'Masukkan token bot dari @BotFather di bawah.' }}
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
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Sesi Kasir Aktif</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text-main);">
                {{ $activeSessions->count() }} Pengguna
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Akun Telegram yang saat ini terautentikasi ke database kasir.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Tautan Webhook</div>
            <div style="font-size: 14px; font-weight: 700; color: var(--success); display: flex; align-items: center; gap: 6px;">
                <x-icon name="check" :size="16" />
                <span>Siap Digunakan</span>
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Webhook otomatis terpasang saat token disimpan.
            </div>
        </div>
    </div>

    <!-- Grid 2 Kolom: Pengaturan Bot & Panduan Setup -->
    <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; margin-bottom: 24px;">
        <!-- Form Pengaturan Bot -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Kredensial Gateway Telegram</span>
                <span class="badge badge-v8">Setup</span>
            </div>

            <form id="botTeleForm" action="{{ route('settings.bot-tele.save') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Token Bot Telegram (dari @BotFather)</label>
                    <input type="text" name="bot_token" class="form-control" value="{{ $setting->decrypted_bot_token }}" placeholder="Contoh: 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz..." required>
                    <div class="form-text">
                        Buat bot baru di Telegram via <a href="https://t.me/BotFather" target="_blank" style="color: var(--primary); text-decoration: underline;">@BotFather</a>, salin token yang diberikan, lalu simpan di sini.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Koneksi Database Toko Acosys V8</label>
                    <select name="connection_id" class="form-control">
                        @foreach($account->connections as $conn)
                            <option value="{{ $conn->id }}" {{ $setting->connection_id == $conn->id ? 'selected' : '' }}>
                                [{{ strtoupper($conn->acosys_version ?? 'v4') }}] {{ $conn->label }} ({{ $conn->database }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Pilih database kasir Acosys V8 yang akan diakses pengguna Telegram.</div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 600;">
                        <input type="checkbox" name="is_active" value="1" {{ $setting->is_active ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        <span>Aktifkan Layanan Bot Telegram</span>
                    </label>
                    <div class="form-text" style="margin-left: 28px;">Jika dinonaktifkan, bot tidak akan merespons pesan dari Telegram.</div>
                </div>

                <div style="background: var(--bg-page); border: 1px solid var(--border-subtle); padding: 12px; border-radius: var(--radius-md); margin-top: 20px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-subtle); margin-bottom: 4px;">Webhook URL Otomatis</div>
                    <code style="font-size: 11px; word-break: break-all; color: var(--text-main);">{{ $webhookUrl }}</code>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Dipasang otomatis ke Telegram Bot API saat token disimpan.
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan Token & Hubungkan Bot">
                        <x-icon name="save" />
                    </button>
                </div>
            </form>
        </div>

        <!-- Panduan & Pengujian -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Panduan Koneksi & Pengujian</span>
                <span class="badge badge-neutral">Pengujian</span>
            </div>

            <div style="font-size: 13px; line-height: 1.6; color: var(--text-main);">
                <div style="margin-bottom: 12px;">
                    <strong>1. Hubungkan Bot:</strong> Masukkan token bot dan pastikan status terverifikasi hijau.
                </div>
                <div style="margin-bottom: 12px;">
                    <strong>2. Autentikasi Kasir:</strong> Kasir/staf mengirim format <code>username password</code> (pesan password otomatis dihapus bot demi keamanan).
                </div>
                <div style="margin-bottom: 12px;">
                    <strong>3. Modul Terintegrasi:</strong> Menu di dalam bot muncul dinamis sesuai modul Acosys V8 yang aktif dilanggan oleh toko Anda (contoh: <em>Cek Buku Besar</em>).
                </div>
            </div>

            <!-- Form Uji Kirim Pesan -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px;">Uji Kirim Pesan ke Telegram</div>
                <form action="{{ route('settings.bot-tele.test') }}" method="POST" style="display: flex; gap: 8px;">
                    @csrf
                    <input type="text" name="chat_id" class="form-control" placeholder="Masukkan Telegram Chat ID Anda" style="flex: 1;" required>
                    <button type="submit" class="btn-icon btn-neutral" data-tooltip="Kirim Pesan Uji Coba">
                        <x-icon name="send" />
                    </button>
                </form>
                <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                    Gunakan <a href="https://t.me/userinfobot" target="_blank" style="color: var(--primary); text-decoration: underline;">@userinfobot</a> di Telegram untuk melihat Chat ID Anda.
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Sesi Pengguna Telegram yang Sedang Login -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Sesi Kasir Telegram Aktif</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Daftar akun Telegram yang terautentikasi dengan user kasir Acosys V8.
                </div>
            </div>
            <span class="badge badge-neutral">{{ $activeSessions->count() }} Sesi</span>
        </div>

        @if($activeSessions->isEmpty())
            <div style="text-align: center; padding: 30px 20px; color: var(--text-muted); font-size: 13px;">
                Belum ada kasir yang login ke bot. Buka bot di Telegram lalu kirim perintah <code>/start</code>.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Telegram Chat ID</th>
                            <th>Pengguna Kasir Acosys V8</th>
                            <th>Role / Hak Akses</th>
                            <th>Waktu Login / Terakhir Aktif</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activeSessions as $ses)
                            <tr>
                                <td><code>{{ $ses->chat_id }}</code></td>
                                <td><strong>{{ $ses->acosys_user_name }}</strong> (ID: {{ $ses->acosys_user_id }})</td>
                                <td><span class="badge badge-neutral">{{ $ses->acosys_user_role ?: 'User' }}</span></td>
                                <td>{{ $ses->last_active_at ? $ses->last_active_at->diffForHumans() : '-' }}</td>
                                <td style="text-align: center;"><span class="badge badge-success">Terhubung</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
