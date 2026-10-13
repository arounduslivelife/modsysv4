<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Daftar Akun Usaha (Tenants)</h1>
            <p>Daftar seluruh toko/tenant yang terdaftar di platform modsysv4.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Dashboard Admin">
                <x-icon name="arrow-left" />
            </a>
            <a href="{{ route('admin.accounts') }}" class="btn-icon btn-primary" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Usaha</th>
                        <th>Owner</th>
                        <th>Koneksi Database</th>
                        <th>Modul Aktif</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $acc)
                        <tr>
                            <td><strong>{{ $acc->name }}</strong></td>
                            <td>{{ $acc->owner?->name }} ({{ $acc->owner?->email }})</td>
                            <td>{{ $acc->connections->count() }} Cabang</td>
                            <td>
                                @php
                                    $activeMods = $acc->accountModules->where('is_active', true);
                                @endphp
                                @if($activeMods->isNotEmpty())
                                    <span class="badge badge-success">{{ $activeMods->count() }} Modul</span>
                                @else
                                    <span class="badge badge-neutral">Belum Ada</span>
                                @endif
                            </td>
                            <td><span class="badge badge-success">{{ ucfirst($acc->status) }}</span></td>
                            <td>{{ $acc->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="table-actions">
                                    <a href="{{ route('admin.accounts.detail', $acc->id) }}" class="btn-icon btn-sm btn-primary" data-tooltip="Kelola Modul & Expired">
                                        <x-icon name="settings" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 16px;">
            {{ $accounts->links() }}
        </div>
    </div>
</x-app-layout>
