<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Manajemen Staf & Hak Akses</h1>
            <p>Undang tim atau kasir toko untuk mengakses modul toko {{ $account->name }}.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn-icon btn-primary" onclick="document.getElementById('addStaffCard').scrollIntoView({behavior:'smooth'})" data-tooltip="Undang Staf Baru">
                <x-icon name="plus" />
            </button>
            <a href="{{ route('settings.staff') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Anggota Terdaftar -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Anggota Akun Usaha ({{ $members->count() }})</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Pengguna</th>
                        <th>Email Google</th>
                        <th>Peran (Role)</th>
                        <th>Status</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $m)
                        <tr>
                            <td>
                                <strong>{{ $m->user?->name ?: 'Belum Login' }}</strong>
                            </td>
                            <td>{{ $m->user?->email }}</td>
                            <td>
                                <span class="badge {{ $m->role === 'owner' ? 'badge-primary' : 'badge-neutral' }}">
                                    {{ ucfirst($m->role) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success">{{ ucfirst($m->status) }}</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    @if($m->user_id !== auth()->id())
                                        <form action="{{ route('settings.staff.delete', $m->id) }}" method="POST" onsubmit="return confirm('Cabut akses staf ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-icon btn-sm btn-danger" data-tooltip="Cabut Akses">
                                                <x-icon name="trash" />
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
    </div>

    <!-- Form Tambah Staf -->
    <div class="card" id="addStaffCard">
        <div class="card-header">
            <span class="card-title">Undang Anggota / Staf Baru</span>
            <span style="font-size: 12px; color: var(--text-muted);">Staf akan otomatis terhubung saat login dengan email Google yang Anda masukkan</span>
        </div>

        <form action="{{ route('settings.staff.invite') }}" method="POST">
            @csrf
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Email Akun Google Staf</label>
                    <input type="email" name="email" class="form-control" placeholder="kasir.toko@gmail.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Peran (Role)</label>
                    <select name="role" class="form-control" required>
                        <option value="staff">Staf (Akses Modul Toko)</option>
                        <option value="owner">Co-Owner (Akses Penuh)</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
                <button type="submit" class="btn-icon btn-primary" data-tooltip="Kirim Undangan">
                    <x-icon name="plus" />
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
