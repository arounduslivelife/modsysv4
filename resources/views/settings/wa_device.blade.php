<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Perangkat WhatsApp Gateway</h1>
            <p>Tautkan nomor WhatsApp pengirim (sender) toko Anda untuk mengirimkan notifikasi pengingat piutang.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn-icon btn-primary" onclick="document.getElementById('addDeviceCard').scrollIntoView({behavior:'smooth'})" data-tooltip="Daftarkan Nomor WhatsApp">
                <x-icon name="plus" />
            </button>
            <a href="{{ route('settings.wa-device') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Perangkat Terdaftar -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar WhatsApp Sender Toko ({{ $devices->count() }})</span>
        </div>

        @if($devices->isEmpty())
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <x-icon name="phone" :size="36" style="margin-bottom: 12px; color: var(--text-subtle);" /><br>
                Belum ada nomor WhatsApp sender yang didaftarkan. Silakan daftarkan di form bawah dan scan QR Code.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Label Perangkat</th>
                            <th>Nomor Sender</th>
                            <th>Status Koneksi</th>
                            <th>Tautan Terakhir</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devices as $dev)
                            <tr>
                                <td><strong>{{ $dev->label }}</strong></td>
                                <td><code>{{ $dev->phone ? '+' . $dev->phone : 'Belum Terhubung' }}</code></td>
                                <td>
                                    @if($dev->status === 'connected')
                                        <span class="badge badge-success">Online & Siap</span>
                                    @elseif($dev->status === 'scanning')
                                        <span class="badge badge-warning">Menunggu Scan</span>
                                    @else
                                        <span class="badge badge-danger">Terputus</span>
                                    @endif
                                </td>
                                <td>{{ $dev->connected_at ? $dev->connected_at->diffForHumans() : '-' }}</td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Tombol Scan QR -->
                                        <button type="button" class="btn-icon btn-sm btn-primary" onclick="showQrModal({{ $dev->id }}, '{{ addslashes($dev->label) }}')" data-tooltip="Buka QR Code WhatsApp">
                                            <x-icon name="qr-code" />
                                        </button>

                                        <!-- Hapus -->
                                        <form action="{{ route('settings.wa-device.delete', $dev->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Putuskan dan hapus perangkat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-sm btn-danger" data-tooltip="Putuskan Sambungan">
                                                <x-icon name="trash" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Form Tambah Device -->
    <div class="card" id="addDeviceCard">
        <div class="card-header">
            <span class="card-title">Daftarkan Sesi WhatsApp Baru</span>
            <span style="font-size: 12px; color: var(--text-muted);">Sistem akan membuat sesi aman baru untuk dihubungkan ke WhatsApp Web toko Anda</span>
        </div>

        <form action="{{ route('settings.wa-device.store') }}" method="POST">
            @csrf
            <div class="form-group" style="max-width: 480px;">
                <label class="form-label">Nama / Label WhatsApp</label>
                <input type="text" name="label" class="form-control" placeholder="Contoh: WhatsApp Billing Toko" required>
                <div class="form-text">Beri nama untuk mengenali nomor sender ini.</div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="submit" class="btn-icon btn-primary" data-tooltip="Buat Sesi WhatsApp">
                    <x-icon name="plus" />
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Scan QR Code -->
    <div id="qrModal" class="modal-backdrop">
        <div class="modal-content" style="text-align: center;">
            <div class="modal-header">
                <h3 class="modal-title" id="qrModalTitle">Scan QR WhatsApp</h3>
                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="closeQrModal()" data-tooltip="Tutup">
                    <x-icon name="x" :size="16" />
                </button>
            </div>

            <div id="qrLoading" style="padding: 32px 16px; text-align: center;">
                <div style="display: inline-block; width: 36px; height: 36px; border: 3px solid #E2E8F0; border-top-color: #2F5BEA; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 14px;"></div>
                <div id="qrLoadingText" style="font-size: 13px; font-weight: 500; color: #1E293B;">
                    Menghubungkan ke WhatsApp Gateway...
                </div>
                <div id="qrLoadingSub" style="font-size: 12px; color: #64748B; margin-top: 4px;">
                    Menyiapkan QR Code baru, mohon tunggu sebentar...
                </div>
            </div>

            <div id="qrContainer" style="display: none; padding: 12px 16px;">
                <img id="qrImage" src="" alt="WhatsApp QR Code" style="width: 240px; height: 240px; margin: 0 auto; display: block; border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 8px; background: #fff;">
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">
                    Buka WhatsApp di HP Anda &rarr; <strong>Perangkat Tertaut</strong> &rarr; <strong>Tautkan Perangkat</strong>, lalu arahkan kamera ke QR Code di atas.
                </p>
            </div>

            <div id="qrConnected" style="display: none; padding: 24px 16px;">
                <span class="badge badge-success" style="font-size: 14px; padding: 10px 20px; font-weight: 600;">
                    WhatsApp Berhasil Terhubung!
                </span>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 12px;">
                    Halaman akan otomatis dimuat ulang...
                </p>
            </div>

            <div class="modal-footer" style="justify-content: center; gap: 8px;">
                <button type="button" class="btn-icon btn-neutral" onclick="refreshCurrentQr()" data-tooltip="Muat Ulang QR">
                    <x-icon name="refresh" />
                </button>
                <button type="button" class="btn-icon btn-neutral" onclick="closeQrModal()" data-tooltip="Tutup">
                    <x-icon name="x" />
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        let qrPollInterval = null;
        let currentActiveDeviceId = null;

        function showQrModal(deviceId, label) {
            currentActiveDeviceId = deviceId;
            document.getElementById('qrModalTitle').innerText = 'Scan QR: ' + label;
            document.getElementById('qrLoading').style.display = 'block';
            document.getElementById('qrLoadingText').innerText = 'Menghubungkan ke WhatsApp Gateway...';
            document.getElementById('qrLoadingSub').innerText = 'Menyiapkan QR Code baru, mohon tunggu sebentar...';
            document.getElementById('qrContainer').style.display = 'none';
            document.getElementById('qrConnected').style.display = 'none';
            document.getElementById('qrModal').classList.add('active');

            loadQr(deviceId);
            if (qrPollInterval) clearInterval(qrPollInterval);
            qrPollInterval = setInterval(() => loadQr(deviceId), 3500);
        }

        function refreshCurrentQr() {
            if (currentActiveDeviceId) {
                document.getElementById('qrLoading').style.display = 'block';
                document.getElementById('qrContainer').style.display = 'none';
                document.getElementById('qrLoadingText').innerText = 'Memperbarui QR Code...';
                loadQr(currentActiveDeviceId);
            }
        }

        function loadQr(deviceId) {
            fetch('{{ url("settings/wa-devices") }}/' + deviceId + '/qr')
                .then(r => r.json())
                .then(data => {
                    if (data.connection_status === 'connected') {
                        document.getElementById('qrLoading').style.display = 'none';
                        document.getElementById('qrContainer').style.display = 'none';
                        document.getElementById('qrConnected').style.display = 'block';
                        clearInterval(qrPollInterval);
                        setTimeout(() => location.reload(), 2000);
                    } else if (data.qr) {
                        document.getElementById('qrLoading').style.display = 'none';
                        document.getElementById('qrImage').src = data.qr;
                        document.getElementById('qrContainer').style.display = 'block';
                    } else {
                        // Hanya tampilkan loading jika QR belum pernah tampil di layar
                        if (document.getElementById('qrContainer').style.display !== 'block') {
                            document.getElementById('qrLoading').style.display = 'block';
                            const status = data.connection_status || 'menunggu';
                            document.getElementById('qrLoadingText').innerText = 'Status: ' + status;
                            if (data.last_error) {
                                document.getElementById('qrLoadingSub').innerText = 'Info: ' + data.last_error;
                            } else {
                                document.getElementById('qrLoadingSub').innerText = 'Sedang membuat sesi QR WhatsApp...';
                            }
                        }
                    }
                })
                .catch(err => {
                    if (document.getElementById('qrContainer').style.display !== 'block') {
                        document.getElementById('qrLoading').style.display = 'block';
                        document.getElementById('qrLoadingText').innerText = 'Gagal memuat QR Code';
                        document.getElementById('qrLoadingSub').innerText = 'Error: ' + err.message;
                    }
                });
        }

        function closeQrModal() {
            if (qrPollInterval) clearInterval(qrPollInterval);
            document.getElementById('qrModal').classList.remove('active');
        }
    </script>
</x-app-layout>
