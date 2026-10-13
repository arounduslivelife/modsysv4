<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Permintaan Aktivasi Modul</h1>
            <p>Daftar permintaan aktivasi dari pengguna yang mengklik modul abu-abu.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Dashboard">
                <x-icon name="arrow-left" />
            </a>
            <a href="{{ route('admin.requests') }}" class="btn-icon btn-primary" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <div class="card">
        @if($requests->isEmpty())
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                Tidak ada riwayat permintaan modul saat ini.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Toko / Akun</th>
                            <th>Pemohon</th>
                            <th>Modul</th>
                            <th>Catatan User</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $req)
                            <tr>
                                <td><strong>{{ $req->account?->name }}</strong></td>
                                <td>{{ $req->user?->name }} ({{ $req->user?->email }})</td>
                                <td><strong>{{ $req->module?->name }}</strong></td>
                                <td><small>{{ $req->note ?: '-' }}</small></td>
                                <td>
                                    @if($req->status === 'approved')
                                        <span class="badge badge-success">Disetujui</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="badge badge-danger">Ditolak</span>
                                    @else
                                        <span class="badge badge-warning">Menunggu</span>
                                    @endif
                                </td>
                                <td>{{ $req->created_at->diffForHumans() }}</td>
                                <td>
                                    <div class="table-actions">
                                        @if($req->status === 'pending')
                                            <form action="{{ route('admin.requests.approve', $req->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn-icon btn-sm btn-success" data-tooltip="Setujui (+30 Hari)">
                                                    <x-icon name="check" />
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.requests.reject', $req->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn-icon btn-sm btn-danger" data-tooltip="Tolak">
                                                    <x-icon name="x" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 16px;">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
