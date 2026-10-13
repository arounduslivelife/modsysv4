<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Koneksi Database Acosys V4</h1>
            <p>Atur kredensial koneksi MariaDB/MySQL desktop Acosys (IP ZeroTier, Tailscale, atau IP publik).</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn-icon btn-primary" onclick="document.getElementById('addConnCard').scrollIntoView({behavior:'smooth'})" data-tooltip="Tambah Koneksi Baru">
                <x-icon name="plus" />
            </button>
            <a href="{{ route('settings.connections') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Daftar Koneksi Aktif -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Koneksi Tersimpan ({{ $connections->count() }})</span>
        </div>

        @if($connections->isEmpty())
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <x-icon name="database" :size="36" style="margin-bottom: 12px; color: var(--text-subtle);" /><br>
                Belum ada database Acosys yang didaftarkan. Silakan isi form di bawah untuk menghubungkan toko Anda.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cabang / Label</th>
                            <th>Host Server</th>
                            <th>Port</th>
                            <th>Database</th>
                            <th>User</th>
                            <th>Status Terakhir</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($connections as $conn)
                            <tr>
                                <td>
                                    <strong>{{ $conn->label }}</strong>
                                    @if(session('active_connection_id') == $conn->id)
                                        <span class="badge badge-success" style="margin-left: 6px;">Aktif</span>
                                    @endif
                                </td>
                                <td><code>{{ $conn->host }}</code></td>
                                <td>{{ $conn->port }}</td>
                                <td><code>{{ $conn->database }}</code></td>
                                <td>{{ $conn->username }}</td>
                                <td>
                                    @if($conn->last_test_ok)
                                        <span class="badge badge-success" title="{{ $conn->test_message }}">Terhubung</span>
                                    @else
                                        <span class="badge badge-danger" title="{{ $conn->test_message }}">Gagal</span>
                                    @endif
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                        {{ $conn->last_test_at ? $conn->last_test_at->diffForHumans() : '-' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Test Koneksi -->
                                        <form action="{{ route('settings.connections.test', $conn->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn-icon btn-sm btn-neutral" data-tooltip="Uji Ulang Koneksi">
                                                <x-icon name="refresh" />
                                            </button>
                                        </form>

                                        <!-- Hapus -->
                                        <form action="{{ route('settings.connections.delete', $conn->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Hapus konfigurasi koneksi ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-sm btn-danger" data-tooltip="Hapus Koneksi">
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

    <!-- Form Tambah Koneksi -->
    <div class="card" id="addConnCard">
        <div class="card-header">
            <span class="card-title">Tambah Kredensial Database Acosys</span>
            <span style="font-size: 12px; color: var(--text-muted);">Password akan otomatis dienkripsi dengan standar AES-256</span>
        </div>

        <form action="{{ route('settings.connections.store') }}" method="POST">
            @csrf
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Cabang / Label</label>
                    <input type="text" name="label" class="form-control" placeholder="Contoh: Toko Pusat, Cabang Sudirman" required>
                    <div class="form-text">Label pembeda jika Anda memiliki lebih dari 1 cabang/server.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Host / IP Database Acosys</label>
                    <input type="text" name="host" class="form-control" placeholder="10.x.x.x atau 100.x.x.x (ZeroTier/Tailscale)" required>
                    <div class="form-text">Masukkan IP komputer Acosys lokal yang telah dipasangi ZeroTier/Tailscale, atau domain publik.</div>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" name="port" class="form-control" value="3306" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Database</label>
                    <input type="text" name="database" class="form-control" placeholder="db_acosys" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Username Database</label>
                    <input type="text" name="username" class="form-control" placeholder="db_acosys / root" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password Database</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan & Uji Koneksi">
                    <x-icon name="save" />
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
