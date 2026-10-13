<x-app-layout>
    <!-- Page Header (Universal Pattern) -->
    <div class="page-header">
        <div class="page-title-box">
            <h1>Beranda Toko</h1>
            <p>Kelola modul SaaS dan pantau koneksi database Acosys untuk {{ $account->name }}.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('settings.connections') }}" class="btn-icon btn-neutral" data-tooltip="Pengaturan Database">
                <x-icon name="database" />
            </a>
            <a href="{{ route('settings.wa-device') }}" class="btn-icon btn-neutral" data-tooltip="Pengaturan WhatsApp">
                <x-icon name="phone" />
            </a>
            <a href="{{ route('dashboard') }}" class="btn-icon btn-primary" data-tooltip="Segarkan Halaman">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Quick Status Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Database Acosys</span>
                <span class="badge {{ $connections->where('last_test_ok', true)->count() > 0 ? 'badge-success' : 'badge-warning' }}">
                    {{ $connections->where('last_test_ok', true)->count() }} Terhubung
                </span>
            </div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text-main);">
                {{ $connections->count() }} Cabang
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                Aktif: {{ $connections->first()?->label ?: 'Belum ada koneksi' }}
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Modul Aktif</span>
                <span class="badge badge-success">
                    {{ $activeModulesCount }} Aktif
                </span>
            </div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text-main);">
                {{ $activeModulesCount }} dari {{ $modules->count() }}
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                Modul siap digunakan di menu navigasi kiri.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">WhatsApp Gateway</span>
                <span class="badge {{ $waDevices->where('status', 'connected')->count() > 0 ? 'badge-success' : 'badge-danger' }}">
                    {{ $waDevices->where('status', 'connected')->count() > 0 ? 'Online' : 'Offline' }}
                </span>
            </div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text-main);">
                {{ $waDevices->where('status', 'connected')->first()?->phone ?: 'Belum Ada' }}
            </div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                Sender pengirim pengingat piutang toko.
            </div>
        </div>
    </div>

    <!-- Modul SaaS Grid -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Katalog Modul SaaS Acosys</span>
            <span style="font-size: 12px; color: var(--text-muted);">Klik modul untuk membuka fitur atau mengajukan aktivasi</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
            @foreach($modules as $mod)
                @php
                    $isActive = $account->hasActiveModule($mod->code);
                    $accMod = $account->accountModules->where('module_id', $mod->id)->first();
                @endphp
                <div style="border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 20px; display: flex; flex-direction: column; justify-content: space-between; background: {{ $isActive ? '#FFFFFF' : '#FAFAFA' }}; opacity: {{ $isActive ? '1' : '0.85' }};">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <div style="width: 38px; height: 38px; border-radius: var(--radius-md); background: {{ $isActive ? 'var(--primary-light)' : 'var(--bg-page)' }}; color: {{ $isActive ? 'var(--primary)' : 'var(--text-subtle)' }}; display: flex; align-items: center; justify-content: center;">
                                <x-icon :name="$mod->icon" :size="20" />
                            </div>
                            @if($isActive)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-neutral">Terkunci</span>
                            @endif
                        </div>
                        <h4 style="font-size: 15px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">{{ $mod->name }}</h4>
                        <p style="font-size: 12px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px;">{{ $mod->description }}</p>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid var(--border-subtle);">
                        <span style="font-size: 12px; font-weight: 600; color: var(--text-main);">{{ $mod->price_info ?: 'Langganan' }}</span>
                        
                        <div>
                            @if($isActive)
                                <a href="{{ route($mod->route ?? 'dashboard') }}" class="btn-icon btn-sm btn-primary" data-tooltip="Buka Modul">
                                    <x-icon name="arrow-left" style="transform: rotate(180deg);" />
                                </a>
                            @else
                                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="openModuleRequestModal({{ $mod->id }}, '{{ addslashes($mod->name) }}', '{{ addslashes($mod->price_info) }}', '{{ addslashes($mod->description) }}')" data-tooltip="Minta Aktivasi Modul">
                                    <x-icon name="lock" />
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
