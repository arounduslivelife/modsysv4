<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>
                Laporan Hutang
                <span class="badge badge-v8" style="vertical-align: middle; margin-left: 8px;">Acosys V8</span>
            </h1>
            <p>Pemantauan saldo kewajiban hutang usaha per pemasok/vendor, jadwal jatuh tempo, dan rincian faktur pembelian belum lunas realtime via Bot Telegram & Web.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.v8-laporan-hutang.index') }}" class="btn-icon btn-neutral" data-tooltip="Segarkan Data">
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
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Total Hutang Usaha</div>
            <div style="font-size: 22px; font-weight: 700; color: #DC2626;">
                {{ $summary['formatted_total'] }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Total kewajiban pembayaran dari seluruh faktur pembelian aktif.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Pemasok Berhutang</div>
            <div style="font-size: 22px; font-weight: 700; color: var(--text-main);">
                {{ $summary['total_suppliers'] }} Supplier/Vendor
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Pemasok dengan saldo hutang belum terselesaikan.
            </div>
        </div>

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
                Akses cepat via perintah <code>/hutang</code> di Telegram.
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Database Acosys V8</div>
            <div style="font-size: 20px; font-weight: 700; color: var(--text-main);">
                {{ $v8Connection?->label ?: 'Belum Ada' }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
                Tabel audit: <code>supplierbalance</code> & <code>purchase</code>
            </div>
        </div>
    </div>

    <!-- Rincian Faktur Pemasok Tertentu (Jika Dipilih) -->
    @if($supplierDetail)
        <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <h2 style="font-size: 18px; font-weight: 700; margin: 0;">{{ $supplierDetail['name'] }}</h2>
                        <span class="badge badge-neutral" style="font-family: monospace;">{{ $supplierDetail['id'] }}</span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted);">
                        Telepon: <strong>{{ $supplierDetail['phone'] }}</strong> &bull; Alamat: <strong>{{ $supplierDetail['address'] }}</strong>
                    </div>
                </div>
                <div style="text-align: right; display: flex; align-items: center; gap: 12px;">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Sisa Hutang</div>
                        <div style="font-size: 20px; font-weight: 800; color: #DC2626;">{{ $supplierDetail['formatted_balance'] }}</div>
                    </div>
                    <a href="{{ route('modules.v8-laporan-hutang.index', ['q' => $searchQuery]) }}" class="btn-icon btn-neutral" data-tooltip="Tutup Rincian">
                        <x-icon name="close" />
                    </a>
                </div>
            </div>

            <div style="font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
                Daftar Faktur Pembelian Belum Lunas ({{ count($supplierDetail['invoices']) }} Lembar)
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Faktur Pembelian</th>
                            <th>Tanggal Pembelian</th>
                            <th>Jatuh Tempo</th>
                            <th style="text-align: right;">Total Nilai Pembelian</th>
                            <th style="text-align: right;">Sisa Hutang</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($supplierDetail['invoices'] as $inv)
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; color: var(--primary);">{{ $inv['invoice_no'] }}</strong>
                                </td>
                                <td>{{ date('d/m/Y', strtotime($inv['invoice_date'])) }}</td>
                                <td>
                                    {{ !empty($inv['due_date']) ? date('d/m/Y', strtotime($inv['due_date'])) : '-' }}
                                </td>
                                <td style="text-align: right;">{{ $inv['formatted_total'] }}</td>
                                <td style="text-align: right; font-weight: 700; color: #DC2626;">{{ $inv['formatted_remaining'] }}</td>
                                <td style="text-align: center;">
                                    @if($inv['is_overdue'])
                                        <span class="badge badge-danger">Lewat Jatuh Tempo</span>
                                    @else
                                        <span class="badge badge-warning">Belum Lunas</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                    Tidak ada faktur pembelian belum lunas untuk pemasok ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Form Pencarian Pemasok -->
    <div class="card" style="margin-bottom: 24px; padding: 20px;">
        <form method="GET" action="{{ route('modules.v8-laporan-hutang.index') }}" style="display: flex; gap: 12px; align-items: center;">
            <div style="flex: 1; position: relative;">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="Cari nama pemasok atau ID pemasok (contoh: Pabrik, S0033)..." class="form-control" style="width: 100%; padding-left: 36px; height: 42px;">
                <div style="position: absolute; left: 12px; top: 12px; color: var(--text-muted);">
                    <x-icon name="search" />
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 20px;">
                Cari Hutang
            </button>
            @if($searchQuery !== '')
                <a href="{{ route('modules.v8-laporan-hutang.index') }}" class="btn btn-neutral" style="height: 42px; display: inline-flex; align-items: center;">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Hasil Pencarian Pemasok ATAU Top 10 Hutang Terbesar -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; margin: 0;">
                    @if($searchQuery !== '')
                        Hasil Pencarian: "{{ $searchQuery }}" ({{ count($searchResults) }} Pemasok)
                    @else
                        Top 10 Hutang Pemasok Terbesar
                    @endif
                </h2>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                    @if($searchQuery !== '')
                        Daftar pemasok berhutang yang sesuai dengan kriteria pencarian.
                    @else
                        10 pemasok dengan saldo kewajiban pembayaran terbesar yang perlu diprioritaskan pelunasannya.
                    @endif
                </div>
            </div>
        </div>

        @php
            $displayList = ($searchQuery !== '') ? $searchResults : $topCreditors;
        @endphp

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>ID Pemasok</th>
                        <th>Nama Pemasok / Vendor</th>
                        <th>No. Telepon</th>
                        <th>Alamat</th>
                        <th style="text-align: right;">Sisa Hutang</th>
                        <th style="width: 100px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($displayList as $idx => $d)
                        <tr style="{{ ($selectedSupplierId === $d['supplierid']) ? 'background-color: var(--bg-hover);' : '' }}">
                            <td>{{ $idx + 1 }}</td>
                            <td>
                                <span class="badge badge-neutral" style="font-family: monospace;">{{ $d['supplierid'] }}</span>
                            </td>
                            <td>
                                <strong>{{ $d['name'] }}</strong>
                            </td>
                            <td>{{ $d['phone'] }}</td>
                            <td>{{ $d['address'] ?: '-' }}</td>
                            <td style="text-align: right; font-weight: 700; color: #DC2626;">
                                {{ $d['formatted_balance'] }}
                            </td>
                            <td style="text-align: center;">
                                <a href="{{ route('modules.v8-laporan-hutang.index', ['supplier_id' => $d['supplierid'], 'q' => $searchQuery]) }}" class="btn-icon btn-sm btn-neutral" data-tooltip="Lihat Rincian Faktur">
                                    <x-icon name="eye" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                @if($searchQuery !== '')
                                    Tidak ditemukan data pemasok berhutang untuk kata kunci "<strong>{{ $searchQuery }}</strong>".
                                @else
                                    Tidak ada data hutang pemasok di database Acosys V8.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
