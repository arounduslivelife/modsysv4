<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Panel Administrator SaaS</h1>
            <p>Pusat kontrol pengelolaan pengguna, aktivasi modul, dan pemantauan ekosistem modsysv4.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.requests') }}" class="btn-icon btn-neutral" data-tooltip="Permintaan Modul">
                <x-icon name="bell-ring" />
            </a>
            <a href="{{ route('admin.accounts') }}" class="btn-icon btn-neutral" data-tooltip="Kelola Akun Usaha">
                <x-icon name="shopping-bag" />
            </a>
            <a href="{{ route('admin.dashboard') }}" class="btn-icon btn-primary" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Admin Metric Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Total Pengguna</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--text-main);">{{ $totalUsers }}</div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Akun Google terdaftar</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Akun Usaha (Toko)</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--text-main);">{{ $totalAccounts }}</div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Tenant aktif</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Lisensi Modul Aktif</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--success);">{{ $activeModulesCount }}</div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Berlangganan berjalan</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Permintaan Tertunda</div>
            <div style="font-size: 28px; font-weight: 700; color: {{ $pendingRequestsCount > 0 ? 'var(--danger)' : 'var(--text-main)' }};">
                {{ $pendingRequestsCount }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Menunggu persetujuan admin</div>
        </div>
    </div>

    <!-- Permintaan Modul Terbaru -->
    @if($pendingRequests->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <span class="card-title">Permintaan Aktivasi Modul Baru</span>
                <span class="badge badge-warning">{{ $pendingRequests->count() }} Menunggu</span>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Akun Usaha</th>
                            <th>Pemohon</th>
                            <th>Modul Diminta</th>
                            <th>Catatan</th>
                            <th>Tanggal</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingRequests as $req)
                            <tr>
                                <td><strong>{{ $req->account?->name }}</strong></td>
                                <td>{{ $req->user?->name }} ({{ $req->user?->email }})</td>
                                <td><span class="badge badge-neutral">{{ $req->module?->name }}</span></td>
                                <td><small>{{ $req->note ?: '-' }}</small></td>
                                <td>{{ $req->created_at->diffForHumans() }}</td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Setujui (+30 hari) -->
                                        <form action="{{ route('admin.requests.approve', $req->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn-icon btn-sm btn-success" data-tooltip="Setujui (+30 Hari)">
                                                <x-icon name="check" />
                                            </button>
                                        </form>

                                        <!-- Tolak -->
                                        <form action="{{ route('admin.requests.reject', $req->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn-icon btn-sm btn-danger" data-tooltip="Tolak Permintaan">
                                                <x-icon name="x" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Daftar Akun Usaha Terbaru -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Akun Usaha Terbaru</span>
            <a href="{{ route('admin.accounts') }}" class="btn-icon btn-sm btn-neutral" data-tooltip="Lihat Semua Akun">
                <x-icon name="arrow-left" style="transform: rotate(180deg);" />
            </a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Usaha</th>
                        <th>Owner</th>
                        <th>Koneksi DB</th>
                        <th>Modul Aktif</th>
                        <th>Terdaftar</th>
                        <th style="text-align: right;">Kelola Modul</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentAccounts as $acc)
                        <tr>
                            <td><strong>{{ $acc->name }}</strong></td>
                            <td>{{ $acc->owner?->name }} ({{ $acc->owner?->email }})</td>
                            <td>{{ $acc->connections()->count() }} Cabang</td>
                            <td>
                                <span class="badge badge-success">
                                    {{ $acc->accountModules()->where('is_active', true)->count() }} Modul
                                </span>
                            </td>
                            <td>{{ $acc->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('admin.accounts.detail', $acc->id) }}" class="btn-icon btn-sm btn-primary" data-tooltip="Atur Modul & Kadaluarsa">
                                        <x-icon name="settings" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
