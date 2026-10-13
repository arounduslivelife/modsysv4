<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Kelola Modul: {{ $account->name }}</h1>
            <p>Atur ceklis aktivasi modul dan tentukan tanggal kadaluarsa untuk akun ini.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.accounts') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Daftar Akun">
                <x-icon name="arrow-left" />
            </a>
            <button type="submit" form="moduleChecklistForm" class="btn-icon btn-primary" data-tooltip="Simpan Perubahan Lisensi">
                <x-icon name="save" />
            </button>
        </div>
    </div>

    <!-- Info Akun -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; font-size: 13px;">
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 11px;">PEMILIK (OWNER)</span>
                <strong>{{ $account->owner?->name }}</strong> ({{ $account->owner?->email }})
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 11px;">KONEKSI DATABASE ACOSYS</span>
                <strong>{{ $account->connections->count() }} Cabang</strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 11px;">TOTAL ANGGOTA STAF</span>
                <strong>{{ $account->members->count() }} Pengguna</strong>
            </div>
            <div>
                <span style="color: var(--text-muted); display: block; font-size: 11px;">STATUS AKUN</span>
                <span class="badge badge-success">{{ ucfirst($account->status) }}</span>
            </div>
        </div>
    </div>

    <!-- Form Checklist Modul & Tanggal Kadaluarsa -->
    <form id="moduleChecklistForm" action="{{ route('admin.accounts.modules.update', $account->id) }}" method="POST">
        @csrf
        <div class="card">
            <div class="card-header">
                <span class="card-title">Checklist Modul & Tanggal Kadaluarsa (Expired)</span>
                <span style="font-size: 12px; color: var(--text-muted);">Centang untuk mengaktifkan modul dan tentukan masa aktifnya</span>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">Aktif</th>
                            <th>Nama Modul</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Kadaluarsa (Expired)</th>
                            <th>Preset Cepat</th>
                            <th>Catatan Lisensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allModules as $mod)
                            @php
                                $accMod = $accountModulesMap->get($mod->id);
                                $isActive = $accMod ? $accMod->is_active : false;
                                $startsAt = $accMod && $accMod->starts_at ? $accMod->starts_at->format('Y-m-d') : date('Y-m-d');
                                $expiresAt = $accMod && $accMod->expires_at ? $accMod->expires_at->format('Y-m-d') : '';
                                $note = $accMod ? $accMod->note : '';
                            @endphp
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="modules[{{ $mod->id }}][is_active]" value="1" id="chk_{{ $mod->id }}" {{ $isActive ? 'checked' : '' }} style="width: 18px; height: 18px; cursor: pointer;">
                                </td>
                                <td>
                                    <label for="chk_{{ $mod->id }}" style="cursor: pointer;">
                                        <strong>{{ $mod->name }}</strong>
                                        <div style="font-size: 11px; color: var(--text-muted);">{{ $mod->description }}</div>
                                    </label>
                                </td>
                                <td>
                                    <input type="date" name="modules[{{ $mod->id }}][starts_at]" value="{{ $startsAt }}" class="form-control" style="width: 140px;">
                                </td>
                                <td>
                                    <input type="date" name="modules[{{ $mod->id }}][expires_at]" id="exp_{{ $mod->id }}" value="{{ $expiresAt }}" class="form-control" style="width: 140px;">
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="setPresetDate({{ $mod->id }}, 30)" data-tooltip="+30 Hari">
                                            <span style="font-size: 10px; font-weight: 700;">30H</span>
                                        </button>
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="setPresetDate({{ $mod->id }}, 90)" data-tooltip="+90 Hari">
                                            <span style="font-size: 10px; font-weight: 700;">90H</span>
                                        </button>
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="setPresetDate({{ $mod->id }}, 365)" data-tooltip="+1 Tahun">
                                            <span style="font-size: 10px; font-weight: 700;">1Th</span>
                                        </button>
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="clearPresetDate({{ $mod->id }})" data-tooltip="Tanpa Batas (Permanen)">
                                            <x-icon name="x" :size="12" />
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="modules[{{ $mod->id }}][note]" value="{{ $note }}" class="form-control" placeholder="Contoh: Paket promo tahunan">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
                <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan Perubahan Lisensi">
                    <x-icon name="save" />
                </button>
            </div>
        </div>
    </form>

    <script>
        function setPresetDate(moduleId, days) {
            document.getElementById('chk_' + moduleId).checked = true;
            const targetDate = new Date();
            targetDate.setDate(targetDate.getDate() + days);
            const yyyy = targetDate.getFullYear();
            const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
            const dd = String(targetDate.getDate()).padStart(2, '0');
            document.getElementById('exp_' + moduleId).value = `${yyyy}-${mm}-${dd}`;
        }

        function clearPresetDate(moduleId) {
            document.getElementById('chk_' + moduleId).checked = true;
            document.getElementById('exp_' + moduleId).value = '';
        }
    </script>
</x-app-layout>
