<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'modsysv4' }} — Modul SaaS Acosys</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    @php
        $currentUser = auth()->user();
        $currentAccount = $currentUser ? $currentUser->getActiveAccount() : null;
        $allAccounts = $currentUser ? $currentUser->accounts : collect();
        $allModules = \App\Models\Module::where('is_published', true)->orderBy('sort_order')->get();
        $connections = $currentAccount ? $currentAccount->connections : collect();
        $activeConnectionId = session('active_connection_id') ?? ($connections->first()?->id);
    @endphp

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="app-sidebar">
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}">
                    <img src="{{ asset('brand/logo.svg') }}" alt="modsysv4">
                </a>
            </div>

            <div class="sidebar-nav">
                <div class="nav-section-title">Modul SaaS</div>

                @foreach($allModules as $mod)
                    @php
                        $isActive = $currentAccount && $currentAccount->hasActiveModule($mod->code);
                        $isCurrentRoute = request()->is('modules/' . str_replace('_', '-', $mod->code) . '*');
                    @endphp

                    @if($isActive)
                        <a href="{{ route($mod->route ?? 'dashboard') }}" class="nav-item {{ $isCurrentRoute ? 'active' : '' }}">
                            <x-icon :name="$mod->icon" />
                            <span>{{ $mod->name }}</span>
                        </a>
                    @else
                        <button type="button" class="nav-item disabled-module" onclick="openModuleRequestModal({{ $mod->id }}, '{{ addslashes($mod->name) }}', '{{ addslashes($mod->price_info) }}', '{{ addslashes($mod->description) }}')">
                            <x-icon :name="$mod->icon" />
                            <span>{{ $mod->name }}</span>
                            <x-icon name="lock" class="lock-badge" :size="14" />
                        </button>
                    @endif
                @endforeach

                <div class="nav-section-title">Konfigurasi</div>
                <a href="{{ route('settings.connections') }}" class="nav-item {{ request()->routeIs('settings.connections*') ? 'active' : '' }}">
                    <x-icon name="database" />
                    <span>Koneksi Acosys</span>
                </a>
                <a href="{{ route('settings.wa-device') }}" class="nav-item {{ request()->routeIs('settings.wa-device*') ? 'active' : '' }}">
                    <x-icon name="phone" />
                    <span>WhatsApp Gateway</span>
                </a>
                <a href="{{ route('settings.staff') }}" class="nav-item {{ request()->routeIs('settings.staff*') ? 'active' : '' }}">
                    <x-icon name="users-round" />
                    <span>Staf & Akses</span>
                </a>

                @if($currentUser && $currentUser->isAdmin())
                    <div class="nav-section-title">Administrator</div>
                    <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->is('admin*') ? 'active' : '' }}">
                        <x-icon name="shield" />
                        <span>Panel Admin SaaS</span>
                    </a>
                @endif
            </div>

            <div class="sidebar-footer">
                <form action="{{ route('auth.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="nav-item" style="color: var(--danger);">
                        <x-icon name="log-out" />
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Topbar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <!-- Akun Usaha Switcher -->
                @if($allAccounts->count() > 1)
                    <div class="topbar-select-group">
                        <x-icon name="shopping-bag" :size="16" />
                        <form action="{{ route('account.switch') }}" method="POST" id="accountSwitchForm">
                            @csrf
                            <select name="account_id" onchange="document.getElementById('accountSwitchForm').submit()">
                                @foreach($allAccounts as $acc)
                                    <option value="{{ $acc->id }}" {{ $currentAccount && $currentAccount->id == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @elseif($currentAccount)
                    <div class="topbar-select-group">
                        <x-icon name="shopping-bag" :size="16" />
                        <span>{{ $currentAccount->name }}</span>
                    </div>
                @endif

                <!-- Koneksi DB Switcher -->
                @if($connections->count() > 0)
                    <div class="topbar-select-group">
                        <x-icon name="database" :size="16" />
                        <form action="{{ route('connection.switch') }}" method="POST" id="connectionSwitchForm">
                            @csrf
                            <select name="connection_id" onchange="document.getElementById('connectionSwitchForm').submit()">
                                @foreach($connections as $conn)
                                    <option value="{{ $conn->id }}" {{ $activeConnectionId == $conn->id ? 'selected' : '' }}>
                                        {{ $conn->label }} ({{ $conn->database }})
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                @endif
            </div>

            <div class="topbar-right">
                <div class="user-badge">
                    <div class="user-avatar">
                        @if($currentUser && $currentUser->avatar)
                            <img src="{{ $currentUser->avatar }}" alt="{{ $currentUser->name }}">
                        @else
                            {{ strtoupper(substr($currentUser->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 13px;">{{ $currentUser->name ?? 'Pengguna' }}</div>
                        <div style="font-size: 11px; color: var(--text-muted);">{{ $currentUser->email ?? '' }}</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="app-main">
            @if(session('success'))
                <div class="alert alert-success">
                    <span>{{ session('success') }}</span>
                    <button type="button" class="btn-icon btn-sm" onclick="this.parentElement.remove()" style="border:none;">
                        <x-icon name="x" :size="14" />
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    <span>{{ session('error') }}</span>
                    <button type="button" class="btn-icon btn-sm" onclick="this.parentElement.remove()" style="border:none;">
                        <x-icon name="x" :size="14" />
                    </button>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info">
                    <span>{{ session('info') }}</span>
                    <button type="button" class="btn-icon btn-sm" onclick="this.parentElement.remove()" style="border:none;">
                        <x-icon name="x" :size="14" />
                    </button>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    <!-- Universal Modal Request Modul -->
    <div id="moduleRequestModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="mModalTitle">Aktifkan Modul</h3>
                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="closeModuleRequestModal()" data-tooltip="Tutup">
                    <x-icon name="x" :size="16" />
                </button>
            </div>
            <form action="{{ route('module.request') }}" method="POST">
                @csrf
                <input type="hidden" name="module_id" id="mModalModuleId">
                <p id="mModalDesc" style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;"></p>
                <div class="alert alert-info" style="margin-bottom: 16px;">
                    <div>
                        <strong>Harga Lisensi:</strong> <span id="mModalPrice"></span><br>
                        <small>Aktivasi dilakukan manual oleh tim admin modsysv4.</small>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (opsional)</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="Contoh: Tolong aktifkan untuk cabang toko utama"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-icon btn-neutral" onclick="closeModuleRequestModal()" data-tooltip="Batal">
                        <x-icon name="x" />
                    </button>
                    <button type="submit" class="btn-icon btn-primary" data-tooltip="Kirim Permintaan Aktivasi">
                        <x-icon name="send" />
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModuleRequestModal(id, name, price, desc) {
            document.getElementById('mModalModuleId').value = id;
            document.getElementById('mModalTitle').innerText = 'Beli Modul: ' + name;
            document.getElementById('mModalPrice').innerText = price || 'Hubungi Admin';
            document.getElementById('mModalDesc').innerText = desc || 'Modul ini belum aktif untuk akun toko Anda.';
            document.getElementById('moduleRequestModal').classList.add('active');
        }

        function closeModuleRequestModal() {
            document.getElementById('moduleRequestModal').classList.remove('active');
        }
    </script>
</body>
</html>
