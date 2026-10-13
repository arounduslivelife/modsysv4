<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Daftar Pengguna Google</h1>
            <p>Pengguna yang terdaftar dan login ke sistem modsysv4.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Dashboard">
                <x-icon name="arrow-left" />
            </a>
            <a href="{{ route('admin.users') }}" class="btn-icon btn-primary" data-tooltip="Segarkan">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Email</th>
                        <th>Peran Sistem</th>
                        <th>Jumlah Akun</th>
                        <th>Login Terakhir</th>
                        <th>Terdaftar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td><strong>{{ $u->name }}</strong></td>
                            <td>{{ $u->email }}</td>
                            <td>
                                @if($u->isAdmin())
                                    <span class="badge badge-success">Admin SaaS</span>
                                @else
                                    <span class="badge badge-neutral">User Reguler</span>
                                @endif
                            </td>
                            <td>{{ $u->accounts_count }} Toko</td>
                            <td>{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : '-' }}</td>
                            <td>{{ $u->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 16px;">
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
