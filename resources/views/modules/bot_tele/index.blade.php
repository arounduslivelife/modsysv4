<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Bot Tele (Telegram Bot)
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Integrasi Bot Telegram cerdas untuk monitoring saldo buku besar pembayaran kasir langsung dari database Acosys V8.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.bot-tele.index') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Data">
                <x-icon name="refresh" />
            </a>
            <button type="submit" form="botTeleForm" class="btn-icon btn-primary" data-tooltip="Simpan Pengaturan Bot">
                <x-icon name="save" />
            </button>
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
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Akun Pembayaran (Payment Types)</div>
            <div style="font-size: 24px; font-weight: 700; color: #6D28D9;">
                {{ count($paymentAccounts) }} Akun Kasir
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Tersedia untuk dicek saldonya via bot Telegram.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Sesi Login Aktif</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text-main);">
                {{ $activeSessions->count() }} Pengguna
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                User Telegram yang saat ini terautentikasi.
            </div>
        </div>
    </div>

    <!-- Grid 2 Kolom: Pengaturan Bot & Panduan Alur -->
    <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 24px; margin-bottom: 24px;">
        <!-- Form Pengaturan Bot -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Pengaturan Bot Telegram</span>
                <span class="badge badge-v8">Setup Cepat</span>
            </div>

            <form id="botTeleForm" action="{{ route('modules.bot-tele.settings.save') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label class="form-label">Token Bot Telegram (dari @BotFather)</label>
                    <input type="text" name="bot_token" class="form-control" value="{{ $setting->decrypted_bot_token }}" placeholder="Contoh: 1234567890:ABCdefGHIjklMNOpqrsTUVwxyz..." required>
                    <div class="form-text">
                        Cukup buat bot di Telegram via <a href="https://t.me/BotFather" target="_blank" style="color: var(--primary); text-decoration: underline;">@BotFather</a>, salin token bot yang diberikan, lalu tempel di sini. Webhook otomatis terhubung!
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
                    <div class="form-text">Pilih database Acosys V8 yang akan diakses pengguna saat mengecek buku besar kasir.</div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 600;">
                        <input type="checkbox" name="is_active" value="1" {{ $setting->is_active ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        <span>Aktifkan Layanan Bot Telegram</span>
                    </label>
                    <div class="form-text" style="margin-left: 28px;">Jika dinonaktifkan, bot tidak akan merespons pesan Telegram sementara waktu.</div>
                </div>

                <div style="background: var(--bg-page); border: 1px solid var(--border-subtle); padding: 12px; border-radius: var(--radius-md); margin-top: 20px;">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-subtle); margin-bottom: 4px;">Webhook URL Otomatis</div>
                    <code style="font-size: 11px; word-break: break-all; color: var(--text-main);">{{ $webhookUrl }}</code>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        Telah dipasang otomatis ke server Telegram saat Anda menyimpan token.
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
                    <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan Token & Hubungkan Bot">
                        <x-icon name="save" />
                    </button>
                </div>
            </form>
        </div>

        <!-- Panduan & Cara Kerja Bot Telegram -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Cara Kerja & Panduan Pengguna Bot</span>
                <span class="badge badge-neutral">Panduan</span>
            </div>

            <div style="font-size: 13px; line-height: 1.6; color: var(--text-main);">
                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #F5F3FF; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 12px;">1</div>
                    <div>
                        <strong>Mulai Chat (/start):</strong><br>
                        <span style="color: var(--text-muted); font-size: 12px;">Siapapun dapat membuka bot Telegram Anda. Saat pertama kali memulai, bot akan meminta login kasir.</span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #F5F3FF; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 12px;">2</div>
                    <div>
                        <strong>Login Kasir (Tabel Users Acosys V8):</strong><br>
                        <span style="color: var(--text-muted); font-size: 12px;">Pengguna mengirimkan format: <code>username password</code> (contoh: <code>admin 123456</code>). Kredensial diverifikasi langsung ke tabel <code>users</code> database Acosys V8. Pesan password otomatis dihapus demi keamanan.</span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-bottom: 14px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #F5F3FF; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 12px;">3</div>
                    <div>
                        <strong>Menu Saldo Buku Besar Kasir:</strong><br>
                        <span style="color: var(--text-muted); font-size: 12px;">Setelah login, bot memunculkan menu <code>[ 💰 Cek Saldo Buku Besar ]</code>. Bot hanya menampilkan akun-akun kas/bank yang terdaftar di tabel <code>paymenttype</code> Acosys V8.</span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #F5F3FF; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; font-size: 12px;">4</div>
                    <div>
                        <strong>Cek Saldo Real-time & Logout:</strong><br>
                        <span style="color: var(--text-muted); font-size: 12px;">Pengguna mengklik salah satu akun, bot langsung menghitung saldo debit/kredit dari tabel <code>journaltrans</code> secara live. Pengguna dapat logout kapan saja dengan tombol <code>[ 🔒 Logout ]</code>.</span>
                    </div>
                </div>
            </div>

            <!-- Form Uji Kirim Pesan -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle);">
                <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px;">Uji Kirim Notifikasi ke Telegram</div>
                <form action="{{ route('modules.bot-tele.test') }}" method="POST" style="display: flex; gap: 8px;">
                    @csrf
                    <input type="text" name="chat_id" class="form-control" placeholder="Masukkan Telegram Chat ID Anda" style="flex: 1;" required>
                    <button type="submit" class="btn-icon btn-neutral" data-tooltip="Kirim Pesan Uji Coba">
                        <x-icon name="send" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Akun Buku Besar Paymenttype (Sesuai Syarat User) -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Daftar Akun Buku Besar Kasir (Tabel paymenttype)</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Hanya akun-akun <code>paymentacc</code> berikut yang akan dimunculkan saat staf/kasir meminta cek saldo buku besar di Telegram.
                </div>
            </div>
            <span class="badge badge-v8">{{ count($paymentAccounts) }} Akun Terhubung</span>
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
                            <th style="text-align: right;">Saldo Terakhir (journaltrans)</th>
                            <th style="text-align: center;">Tampil di Bot</th>
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

    <!-- Tabel Pengguna Telegram yang Sedang Login -->
    <div class="card">
        <div class="card-header">
            <div>
                <span class="card-title">Pengguna Telegram yang Sedang Login (Sesi Aktif)</span>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                    Daftar akun Telegram yang terautentikasi dengan user kasir Acosys V8.
                </div>
            </div>
            <span class="badge badge-neutral">{{ $activeSessions->count() }} Sesi</span>
        </div>

        @if($activeSessions->isEmpty())
            <div style="text-align: center; padding: 30px 20px; color: var(--text-muted); font-size: 13px;">
                Belum ada pengguna Telegram yang login ke bot. Silakan buka bot Anda di Telegram dan ketik <code>/start</code>.
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
