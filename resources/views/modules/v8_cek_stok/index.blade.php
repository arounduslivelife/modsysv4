<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Cek Stok Produk
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Pengecekan sisa stok fisik barang, status persediaan minimum, dan harga jual kasir realtime via Bot Telegram & Web terhubung database Acosys V8.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.v8-cek-stok.index') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Data">
                <x-icon name="refresh" />
            </a>
            <a href="{{ route('settings.bot-tele') }}" class="btn-icon btn-primary" data-tooltip="Konfigurasi Gateway Bot Telegram">
                <x-icon name="settings" />
            </a>
        </div>
    </div>

    <!-- Alert jika koneksi Acosys V8 bermasalah -->
    @if($connectionError)
        <div class="alert alert-danger">
            <div>
                <strong>Peringatan Koneksi Acosys V8:</strong> {{ $connectionError }}<br>
                <small>Pastikan koneksi database Acosys V8 Anda aktif di menu Pengaturan Koneksi.</small>
            </div>
            <a href="{{ route('settings.connections') }}" class="btn-icon btn-sm btn-neutral" data-tooltip="Periksa Kredensial Database">
                <x-icon name="database" />
            </a>
        </div>
    @endif

    <!-- Status Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Gateway Bot Telegram</div>
            <div style="font-size: 20px; font-weight: 700; color: {{ $setting?->last_test_ok ? 'var(--success)' : 'var(--text-muted)' }}; display: flex; align-items: center; gap: 8px;">
                @if($setting?->last_test_ok && $setting?->bot_username)
                    <span>@<span style="color: #6D28D9;">{{ $setting->bot_username }}</span></span>
                    <span class="badge badge-success" style="font-size: 10px;">Aktif</span>
                @else
                    <span>Belum Terhubung</span>
                    <span class="badge badge-warning" style="font-size: 10px;">Perlu Token</span>
                @endif
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                @if($setting?->bot_username)
                    Kasir/staf dapat menekan tombol menu <code>Cek Stok Produk</code> di Telegram.
                @else
                    Atur token bot di menu <a href="{{ route('settings.bot-tele') }}" style="color: var(--primary); text-decoration: underline;">Konfigurasi &rarr; Bot Tele V8</a>.
                @endif
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Database Acosys V8</div>
            <div style="font-size: 20px; font-weight: 700; color: var(--text-main);">
                {{ $v8Connection?->label ?: 'Belum Ada' }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                DB: <code>{{ $v8Connection?->database ?: '-' }}</code> ({{ $v8Connection?->host ?: '-' }})
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Mode Kalkulasi Stok</div>
            <div style="font-size: 20px; font-weight: 700; color: #6D28D9;">
                Audit Trail Mutasi
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Sumber: <code>inventory</code> (invin - invout) & <code>units</code>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Stok Perlu Perhatian</div>
            <div style="font-size: 22px; font-weight: 700; color: #DC2626;">
                {{ count($criticalProducts) }} Item
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Barang dengan stok habis atau di bawah batas minimum.
            </div>
        </div>
    </div>

    <!-- Banner Pencarian Produk -->
    <div class="card" style="margin-bottom: 24px; padding: 20px;">
        <form method="GET" action="{{ route('modules.v8-cek-stok.index') }}" style="display: flex; gap: 12px; align-items: center;">
            <div style="flex: 1; position: relative;">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Cari nama barang, kode barang, atau barcode..." class="form-control" style="width: 100%; padding-left: 36px; height: 42px;">
                <div style="position: absolute; left: 12px; top: 12px; color: var(--text-muted);">
                    <x-icon name="search" :size="18" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="height: 42px; display: flex; align-items: center; gap: 8px;">
                <x-icon name="search" />
                <span>Cari Stok</span>
            </button>
            @if($searchQuery !== '')
                <a href="{{ route('modules.v8-cek-stok.index') }}" class="btn btn-neutral" style="height: 42px; display: flex; align-items: center;">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Hasil Pencarian Teks -->
    @if($searchQuery !== '')
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Hasil Pencarian untuk: "{{ $searchQuery }}"</span>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                        Ditemukan {{ count($searchResults) }} produk yang cocok.
                    </div>
                </div>
            </div>

            @if(empty($searchResults))
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <x-icon name="package" :size="36" style="margin-bottom: 12px; color: var(--text-subtle);" /><br>
                    Tidak ditemukan produk dengan kata kunci "{{ $searchQuery }}".
                </div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kode Barang</th>
                                <th>Barcode</th>
                                <th>Nama Produk</th>
                                <th>Satuan & Harga</th>
                                <th>Batas Min.</th>
                                <th style="text-align: right;">Sisa Stok Fisik</th>
                                <th>Status</th>
                                <th>Rincian Lokasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searchResults as $p)
                                <tr>
                                    <td><code>{{ $p['id'] }}</code></td>
                                    <td><span style="font-size: 12px; color: var(--text-muted);">{{ $p['aliasid'] }}</span></td>
                                    <td>
                                        <strong>{{ $p['name'] }}</strong>
                                        @if($p['brand'] && $p['brand'] !== '-')
                                            <div style="font-size: 11px; color: var(--text-muted);">Brand: {{ $p['brand'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--text-main);">{{ $p['formatted_price'] }}</span>
                                        <span style="font-size: 11px; color: var(--text-muted);">/ {{ $p['primary_unit'] }}</span>
                                    </td>
                                    <td>{{ number_format($p['minimum'], 0, ',', '.') }} {{ $p['primary_unit'] }}</td>
                                    <td style="text-align: right; font-weight: 700; font-size: 15px; color: {{ $p['total_stock'] <= 0 ? '#DC2626' : '#16A34A' }};">
                                        {{ number_format($p['total_stock'], 0, ',', '.') }} {{ $p['primary_unit'] }}
                                    </td>
                                    <td>
                                        @if($p['total_stock'] <= 0)
                                            <span class="badge badge-danger">Habis</span>
                                        @elseif($p['minimum'] > 0 && $p['total_stock'] <= $p['minimum'])
                                            <span class="badge badge-warning">Kritis</span>
                                        @else
                                            <span class="badge badge-success">Aman</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($p['division_stocks']))
                                            <div style="font-size: 11px; line-height: 1.4;">
                                                @foreach($p['division_stocks'] as $ds)
                                                    <div>{{ $ds['division_name'] }}: <strong>{{ number_format($ds['stock_qty'], 0, ',', '.') }}</strong></div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span style="font-size: 11px; color: var(--text-muted);">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <!-- Daftar Produk Stok Kritis / Perlu Perhatian -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Daftar Produk Stok Kritis & Habis (Acosys V8)</span>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                        Barang-barang dengan sisa stok fisik di bawah batas minimum atau habis.
                    </div>
                </div>
                <span class="badge badge-v8">{{ count($criticalProducts) }} Produk Terpantau</span>
            </div>

            @if(empty($criticalProducts))
                <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <x-icon name="check" :size="36" style="margin-bottom: 12px; color: #16A34A;" /><br>
                    Semua stok produk saat ini dalam batas persediaan aman.
                </div>
            @else
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kode Barang</th>
                                <th>Barcode</th>
                                <th>Nama Produk</th>
                                <th>Harga Jual Kasir</th>
                                <th>Batas Min.</th>
                                <th style="text-align: right;">Sisa Stok</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($criticalProducts as $cp)
                                <tr>
                                    <td><code>{{ $cp['id'] }}</code></td>
                                    <td><span style="font-size: 12px; color: var(--text-muted);">{{ $cp['aliasid'] ?: '-' }}</span></td>
                                    <td><strong>{{ $cp['name'] }}</strong></td>
                                    <td>
                                        Rp {{ number_format((float)($cp['salesprice1'] ?? 0), 0, ',', '.') }}
                                        <span style="font-size: 11px; color: var(--text-muted);">/ {{ $cp['unit_name'] ?: 'PCS' }}</span>
                                    </td>
                                    <td>{{ number_format((float)($cp['minimum'] ?? 0), 0, ',', '.') }}</td>
                                    <td style="text-align: right; font-weight: 700; color: {{ $cp['current_stock'] <= 0 ? '#DC2626' : '#D97706' }};">
                                        {{ number_format((float)$cp['current_stock'], 0, ',', '.') }} {{ $cp['unit_name'] ?: 'PCS' }}
                                    </td>
                                    <td>
                                        @if($cp['current_stock'] <= 0)
                                            <span class="badge badge-danger">Habis</span>
                                        @else
                                            <span class="badge badge-warning">Kritis</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
