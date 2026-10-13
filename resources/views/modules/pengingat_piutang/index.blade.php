<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Pengingat Piutang WhatsApp</h1>
            <p>Daftar faktur belum lunas dan jatuh tempo langsung dari database {{ $connection->label }} ({{ $company['name'] }}).</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.pengingat-piutang.settings') }}" class="btn-icon btn-neutral" data-tooltip="Pengaturan Otomatisasi & Mode Penerima">
                <x-icon name="settings" />
            </a>
            <a href="{{ route('modules.pengingat-piutang.index') }}" class="btn-icon btn-primary" data-tooltip="Segarkan Data Piutang">
                <x-icon name="refresh" />
            </a>
        </div>
    </div>

    <!-- Error Alert jika koneksi Acosys bermasalah -->
    @if($connectionError)
        <div class="alert alert-danger">
            <div>
                <strong>Peringatan Koneksi Acosys:</strong> {{ $connectionError }}<br>
                <small>Pastikan komputer kasir/server Acosys Anda menyala dan IP ZeroTier/Tailscale terhubung.</small>
            </div>
            <a href="{{ route('settings.connections') }}" class="btn-icon btn-sm btn-neutral" data-tooltip="Periksa Kredensial Database">
                <x-icon name="database" />
            </a>
        </div>
    @endif

    <!-- WhatsApp Sender Status Bar -->
    @if(!$connectedDevice)
        <div class="alert alert-warning" style="background: var(--warning-light); color: var(--warning); border: 1px solid #FDE68A;">
            <div>
                <strong>WhatsApp Sender Belum Siap:</strong> Anda belum menghubungkan nomor WhatsApp pengirim. Notifikasi belum dapat dikirimkan.
            </div>
            <a href="{{ route('settings.wa-device') }}" class="btn-icon btn-sm btn-primary" data-tooltip="Hubungkan WhatsApp Sekarang">
                <x-icon name="phone" />
            </a>
        </div>
    @endif

    <!-- Metric Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Total Saldo Piutang</div>
            <div style="font-size: 26px; font-weight: 700; color: var(--danger);">
                Rp {{ number_format($totalPiutang, 0, ',', '.') }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Dari total {{ $totalCustomers }} pelanggan menunggak</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Nota Lewat Jatuh Tempo (Overdue)</div>
            <div style="font-size: 26px; font-weight: 700; color: var(--warning);">
                {{ $totalOverdue }} Faktur
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Prioritas segera diingatkan</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Pengirim WhatsApp (Sender)</div>
            <div style="font-size: 18px; font-weight: 700; color: var(--text-main); margin-top: 4px;">
                {{ $connectedDevice ? '+' . $connectedDevice->phone : 'Offline' }}
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">{{ $connectedDevice?->label ?: 'Tidak ada sender aktif' }}</div>
        </div>
    </div>

    <!-- Filter & Pencarian -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 20px;">
        <form action="{{ route('modules.pengingat-piutang.index') }}" method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" name="q" value="{{ $filters['search'] }}" class="form-control" placeholder="Cari nama pelanggan atau ID...">
            </div>

            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                <input type="checkbox" name="overdue_only" value="1" {{ !empty($filters['overdue_only']) ? 'checked' : '' }} onchange="this.form.submit()">
                <span>Hanya yang Overdue (Jatuh Tempo Lewat)</span>
            </label>

            <button type="submit" class="btn-icon btn-neutral" data-tooltip="Terapkan Filter">
                <x-icon name="search" />
            </button>
            <a href="{{ route('modules.pengingat-piutang.index') }}" class="btn-icon btn-neutral" data-tooltip="Reset Filter">
                <x-icon name="x" />
            </a>
        </form>
    </div>

    <!-- Tabel Daftar Piutang Pelanggan -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Pelanggan dengan Saldo Piutang ({{ count($receivables) }})</span>
            <span style="font-size: 12px; color: var(--text-muted);">Data bersumber langsung dari tabel sales & salespayments Acosys</span>
        </div>

        @if(empty($receivables))
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <x-icon name="check" :size="36" style="margin-bottom: 12px; color: var(--success);" /><br>
                Tidak ada data piutang tertunggak yang sesuai dengan filter pencarian.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Pelanggan</th>
                            <th>No. WhatsApp</th>
                            <th>Total Piutang</th>
                            <th>Nota Belum Lunas</th>
                            <th>Status Jatuh Tempo</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receivables as $r)
                            <tr>
                                <td>
                                    <strong>{{ $r['customer_name'] }}</strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">
                                        ID: <code>{{ $r['customer_id'] }}</code> · {{ Str::limit($r['address'], 30) }}
                                    </div>
                                </td>
                                <td>
                                    @if($r['opt_out'])
                                        <span class="badge badge-danger">Opt-Out (Jangan Kirim)</span>
                                    @elseif(!empty($r['phone']))
                                        <code>+{{ $r['phone'] }}</code>
                                        @if($r['has_override'])
                                            <span class="badge badge-neutral" style="font-size: 9px;" title="Nomor manual disimpan di SaaS">Manual</span>
                                        @endif
                                    @else
                                        <span class="badge badge-warning">Nomor Belum Ada</span>
                                    @endif
                                </td>
                                <td>
                                    <strong style="color: var(--danger);">
                                        Rp {{ number_format($r['total_piutang'], 0, ',', '.') }}
                                    </strong>
                                </td>
                                <td>
                                    {{ $r['unpaid_count'] }} Nota
                                </td>
                                <td>
                                    @if($r['overdue_count'] > 0)
                                        <span class="badge badge-danger">{{ $r['overdue_count'] }} Overdue</span>
                                    @else
                                        <span class="badge badge-success">Belum Overdue</span>
                                    @endif
                                    @if($r['earliest_due_date'])
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                            Tempo: {{ date('d M Y', strtotime($r['earliest_due_date'])) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <!-- Rincian Nota Modal -->
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="viewInvoices('{{ $r['customer_id'] }}', '{{ addslashes($r['customer_name']) }}')" data-tooltip="Lihat Rincian Faktur">
                                            <x-icon name="line-chart" />
                                        </button>

                                        <!-- Edit Kontak Modal -->
                                        <button type="button" class="btn-icon btn-sm btn-neutral" onclick="openContactModal('{{ $r['customer_id'] }}', '{{ addslashes($r['customer_name']) }}', '{{ $r['phone'] }}', {{ $r['opt_out'] ? 'true' : 'false' }})" data-tooltip="Ubah Nomor WhatsApp / Opt-Out">
                                            <x-icon name="phone" />
                                        </button>

                                        <!-- Kirim Pengingat Instan via WhatsApp -->
                                        @if(!$r['opt_out'])
                                            <form action="{{ route('modules.pengingat-piutang.send-single') }}" method="POST" style="display:inline;" onsubmit="return confirm('Kirim notifikasi WhatsApp ke {{ addslashes($r['customer_name']) }}?')">
                                                @csrf
                                                <input type="hidden" name="customer_id" value="{{ $r['customer_id'] }}">
                                                <input type="hidden" name="customer_name" value="{{ $r['customer_name'] }}">
                                                <input type="hidden" name="phone" value="{{ $r['phone'] }}">
                                                <input type="hidden" name="total_piutang" value="{{ $r['total_piutang'] }}">
                                                <button type="submit" class="btn-icon btn-sm btn-primary" data-tooltip="Kirim Pesan WhatsApp">
                                                    <x-icon name="send" />
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
        @endif
    </div>

    <!-- Modal Rincian Faktur Belum Lunas -->
    <div id="invoicesModal" class="modal-backdrop">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h3 class="modal-title" id="invModalTitle">Rincian Faktur Piutang</h3>
                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="closeInvoicesModal()" data-tooltip="Tutup">
                    <x-icon name="x" :size="16" />
                </button>
            </div>
            
            <div id="invLoading" style="padding: 20px; text-align: center; color: var(--text-muted);">
                Memuat faktur dari database Acosys...
            </div>

            <div id="invContent" class="table-responsive" style="display: none; max-height: 360px; overflow-y: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Faktur</th>
                            <th>Tgl Faktur</th>
                            <th>Jatuh Tempo</th>
                            <th>Total Faktur</th>
                            <th>Dibayar</th>
                            <th>Sisa Tagihan</th>
                        </tr>
                    </thead>
                    <tbody id="invTableBody"></tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-icon btn-neutral" onclick="closeInvoicesModal()" data-tooltip="Tutup">
                    <x-icon name="x" />
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Ubah Nomor / Kontak Override -->
    <div id="contactModal" class="modal-backdrop">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="contactModalTitle">Atur Kontak Pelanggan</h3>
                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="closeContactModal()" data-tooltip="Tutup">
                    <x-icon name="x" :size="16" />
                </button>
            </div>
            <form action="{{ route('modules.pengingat-piutang.contact') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" id="cModalCustId">
                <div class="form-group">
                    <label class="form-label">Nomor WhatsApp Pelanggan</label>
                    <input type="text" name="phone_override" id="cModalPhone" class="form-control" placeholder="628123456789" required>
                    <div class="form-text">Gunakan format angka berawalan 628... atau 08...</div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                        <input type="checkbox" name="opt_out" value="1" id="cModalOptOut">
                        <span>Jangan kirimi notifikasi pengingat piutang (Opt-Out)</span>
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-icon btn-neutral" onclick="closeContactModal()" data-tooltip="Batal">
                        <x-icon name="x" />
                    </button>
                    <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan Kontak">
                        <x-icon name="save" />
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function viewInvoices(customerId, customerName) {
            document.getElementById('invModalTitle').innerText = 'Faktur Belum Lunas: ' + customerName;
            document.getElementById('invLoading').style.display = 'block';
            document.getElementById('invContent').style.display = 'none';
            document.getElementById('invoicesModal').classList.add('active');

            fetch('{{ url("modules/pengingat-piutang/customers") }}/' + encodeURIComponent(customerId) + '/invoices')
                .then(r => r.json())
                .then(res => {
                    document.getElementById('invLoading').style.display = 'none';
                    if (res.status && res.invoices) {
                        const tbody = document.getElementById('invTableBody');
                        tbody.innerHTML = '';
                        res.invoices.forEach(inv => {
                            const tr = document.createElement('tr');
                            const sisa = 'Rp ' + Number(inv.sisa_tagihan).toLocaleString('id-ID');
                            const total = 'Rp ' + Number(inv.total_faktur).toLocaleString('id-ID');
                            const bayar = 'Rp ' + Number(inv.total_dibayar).toLocaleString('id-ID');
                            const badge = inv.is_overdue ? '<span class="badge badge-danger">Overdue</span>' : '<span class="badge badge-success">OK</span>';

                            tr.innerHTML = `
                                <td><code>${inv.salesid}</code></td>
                                <td>${inv.salesdate}</td>
                                <td>${inv.duedate} ${badge}</td>
                                <td>${total}</td>
                                <td>${bayar}</td>
                                <td><strong style="color:var(--danger);">${sisa}</strong></td>
                            `;
                            tbody.appendChild(tr);
                        });
                        document.getElementById('invContent').style.display = 'block';
                    }
                })
                .catch(e => {
                    document.getElementById('invLoading').innerText = 'Gagal mengambil rincian faktur.';
                });
        }

        function closeInvoicesModal() {
            document.getElementById('invoicesModal').classList.remove('active');
        }

        function openContactModal(customerId, customerName, phone, optOut) {
            document.getElementById('cModalCustId').value = customerId;
            document.getElementById('contactModalTitle').innerText = 'Kontak: ' + customerName;
            document.getElementById('cModalPhone').value = phone || '';
            document.getElementById('cModalOptOut').checked = optOut;
            document.getElementById('contactModal').classList.add('active');
        }

        function closeContactModal() {
            document.getElementById('contactModal').classList.remove('active');
        }
    </script>
</x-app-layout>
