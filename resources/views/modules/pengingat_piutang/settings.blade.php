<x-app-layout>
    <div class="page-header">
        <div class="page-title-box">
            <h1>Pengaturan Otomatisasi & Mode Penerima</h1>
            <p>Konfigurasi jadwal pengiriman pengingat dan opsi penerima (Pelanggan / Nomor Tetap / Keduanya).</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('modules.pengingat-piutang.index') }}" class="btn-icon btn-neutral" data-tooltip="Kembali ke Daftar Piutang">
                <x-icon name="arrow-left" />
            </a>
            <button type="submit" form="reminderSettingForm" class="btn-icon btn-primary" data-tooltip="Simpan Pengaturan">
                <x-icon name="save" />
            </button>
        </div>
    </div>

    <!-- Form Pengaturan Pengingat -->
    <form id="reminderSettingForm" action="{{ route('modules.pengingat-piutang.settings.save') }}" method="POST">
        @csrf
        <div class="card">
            <div class="card-header">
                <span class="card-title">Aturan Pengiriman Pengingat</span>
                <span style="font-size: 12px; color: var(--text-muted);">Berlaku untuk koneksi database: {{ $connection->label }}</span>
            </div>

            <div class="form-grid-2">
                <!-- Status Otomatisasi -->
                <div class="form-group">
                    <label class="form-label">Pengiriman Otomatis Harian</label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 8px;">
                        <input type="checkbox" name="enabled" value="1" {{ $setting->enabled ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        <span style="font-size: 13px; font-weight: 600;">Aktifkan Pengiriman Pengingat Otomatis</span>
                    </label>
                    <div class="form-text">Bila aktif, cron server akan mengecek piutang setiap hari dan mengirimkan notifikasi.</div>
                </div>

                <!-- Jam Kirim -->
                <div class="form-group">
                    <label class="form-label">Waktu Pengiriman Harian (WIB)</label>
                    <input type="time" name="send_time" value="{{ substr($setting->send_time, 0, 5) }}" class="form-control" style="max-width: 160px;">
                    <div class="form-text">Contoh: 09:00 WIB di pagi hari kerja.</div>
                </div>
            </div>

            <div class="form-grid-2">
                <!-- Perangkat WhatsApp Sender -->
                <div class="form-group">
                    <label class="form-label">Perangkat Pengirim (Sender WA)</label>
                    <select name="device_id" class="form-control">
                        <option value="">-- Pilih Perangkat WhatsApp --</option>
                        @foreach($waDevices as $dev)
                            <option value="{{ $dev->id }}" {{ $setting->device_id == $dev->id ? 'selected' : '' }}>
                                {{ $dev->label }} ({{ $dev->phone ? '+' . $dev->phone : 'Offline' }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Nomor pengirim yang digunakan untuk mengirim pesan keluar.</div>
                </div>

                <!-- Mode Penerima (Poin 6 & Keputusan 3) -->
                <div class="form-group">
                    <label class="form-label">Mode Penerima Pesan (Receiver)</label>
                    <select name="recipient_mode" class="form-control">
                        <option value="customer" {{ $setting->recipient_mode === 'customer' ? 'selected' : '' }}>
                            1. Hanya Pelanggan (Kirim 1 per 1 ke masing-masing konsumen yang berhutang)
                        </option>
                        <option value="fixed" {{ $setting->recipient_mode === 'fixed' ? 'selected' : '' }}>
                            2. Hanya Nomor Tetap (Kirim rekap total piutang ke nomor Owner/Kolektor)
                        </option>
                        <option value="both" {{ $setting->recipient_mode === 'both' ? 'selected' : '' }}>
                            3. Keduanya (Kirim ke Pelanggan DAN kirim rekap ke Nomor Owner)
                        </option>
                    </select>
                    <div class="form-text">Pilih tujuan notifikasi sesuai kebijakan penagihan toko Anda.</div>
                </div>
            </div>

            <!-- Nomor Receiver Tetap (Untuk Owner/Kolektor) -->
            <div class="form-group">
                <label class="form-label">Nomor Tetap Penerima Rekap (Owner / Kolektor)</label>
                <input type="text" name="fixed_numbers" value="{{ $setting->fixed_numbers }}" class="form-control" placeholder="628123456789, 628987654321">
                <div class="form-text">Pisahkan dengan koma jika lebih dari 1 nomor. Nomor ini akan menerima rekap harian daftar pelanggan yang berhutang.</div>
            </div>
        </div>

        <!-- Card Kustomisasi Template Pesan WhatsApp -->
        <div class="card">
            <div class="card-header">
                <div>
                    <span class="card-title">Kustomisasi Isi Pesan WhatsApp (Template)</span>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Atur format teks pesan penagihan ke pelanggan dan rekap ke nomor owner</div>
                </div>
                <button type="button" class="btn-icon btn-sm btn-neutral" onclick="resetDefaultTemplates()" data-tooltip="Kembalikan Format Bawaan">
                    <x-icon name="refresh" :size="16" />
                </button>
            </div>

            <!-- 1. Template Pesan Pelanggan -->
            <div style="margin-bottom: 28px;">
                <label class="form-label" style="font-weight: 700; font-size: 14px;">1. Template Pesan untuk Pelanggan</label>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">Pesan yang dikirimkan langsung ke nomor WhatsApp masing-masing konsumen yang menunggak.</div>

                <div style="display: grid; grid-template-columns: minmax(320px, 1.2fr) 340px; gap: 24px; align-items: start;">
                    <div>
                        <textarea id="templateCustomerText" name="template_customer" class="control-textarea form-control" rows="12" style="font-family: inherit; font-size: 13px; line-height: 1.6;" oninput="updateCustomerPreview()">{{ $templateCustomer->body }}</textarea>
                        
                        <div style="margin-top: 10px;">
                            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Klik Variabel untuk Menyisipkan:</span>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                <button type="button" class="btn-chip" onclick="insertTag('templateCustomerText', '{nama_pelanggan}')">{nama_pelanggan}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateCustomerText', '{nama_toko}')">{nama_toko}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateCustomerText', '{total_piutang}')">{total_piutang}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateCustomerText', '{jumlah_nota}')">{jumlah_nota}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateCustomerText', '{tanggal_tempo}')">{tanggal_tempo}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live WhatsApp Smartphone Preview Mockup -->
                    <div class="phone-mockup-wrapper">
                        <div class="phone-device">
                            <!-- Side buttons -->
                            <div class="phone-btn phone-btn-volume-up"></div>
                            <div class="phone-btn phone-btn-volume-down"></div>
                            <div class="phone-btn phone-btn-power"></div>

                            <!-- Screen -->
                            <div class="phone-screen">
                                <!-- Dynamic Island -->
                                <div class="phone-island-bar">
                                    <div class="phone-island">
                                        <span class="phone-island-cam"></span>
                                    </div>
                                </div>

                                <!-- Status Bar -->
                                <div class="phone-status-bar">
                                    <span class="status-time">09:41</span>
                                    <div class="status-icons">
                                        <!-- Cellular -->
                                        <svg width="12" height="10" viewBox="0 0 12 10" fill="currentColor">
                                            <rect x="0" y="7" width="2" height="3" rx="0.5"/>
                                            <rect x="3" y="5" width="2" height="5" rx="0.5"/>
                                            <rect x="6" y="3" width="2" height="7" rx="0.5"/>
                                            <rect x="9" y="0" width="2" height="10" rx="0.5"/>
                                        </svg>
                                        <!-- Wifi -->
                                        <svg width="12" height="10" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 4C7.31 4 3.07 5.9 0 8.98L12 21 24 8.98C20.93 5.9 16.69 4 12 4zm0 4c3.48 0 6.64 1.35 9 3.55L12 19.5 3 11.55C5.36 9.35 8.52 8 12 8z"/>
                                        </svg>
                                        <!-- Battery -->
                                        <svg width="18" height="9" viewBox="0 0 24 12" fill="currentColor">
                                            <rect x="1" y="1" width="19" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                            <rect x="3" y="3" width="13" height="6" rx="1"/>
                                            <path d="M22 4.5v3a1.5 1.5 0 001.5-1.5 1.5 1.5 0 00-1.5-1.5z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- WhatsApp App Header -->
                                <div class="wa-app-header">
                                    <div class="wa-header-left">
                                        <svg class="wa-back-btn" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                        <div class="wa-avatar">
                                            <span>{{ strtoupper(substr($connection->label ?? 'T', 0, 1)) }}</span>
                                        </div>
                                        <div class="wa-user-details">
                                            <div class="wa-user-name" title="{{ $connection->label }}">{{ Str::limit($connection->label ?? 'Toko Anda', 15) }}</div>
                                            <div class="wa-user-status">online</div>
                                        </div>
                                    </div>
                                    <div class="wa-header-actions">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-3.5l4 4v-11l-4 4z"/></svg>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.045 15.045 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1z"/></svg>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                                    </div>
                                </div>

                                <!-- WhatsApp Chat Canvas -->
                                <div class="wa-chat-container">
                                    <div class="wa-date-chip">HARI INI</div>

                                    <!-- WhatsApp Chat Bubble (Outgoing) -->
                                    <div class="wa-message-bubble">
                                        <div class="wa-message-text" id="previewCustomerBox"></div>
                                        <div class="wa-message-meta">
                                            <span class="wa-msg-time">{{ now()->format('H:i') }}</span>
                                            <svg class="wa-ticks" width="14" height="10" viewBox="0 0 16 11" fill="none">
                                                <path d="M11.05 0.7L4.7 7.05L1.95 4.3L0.85 5.4L4.7 9.25L12.15 1.8L11.05 0.7Z" fill="#53BDEB"/>
                                                <path d="M15.15 0.7L8.8 7.05L7.9 6.15L6.8 7.25L8.8 9.25L16.25 1.8L15.15 0.7Z" fill="#53BDEB"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- WhatsApp Input Footer -->
                                <div class="wa-chat-footer">
                                    <div class="wa-input-pill">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                                        </svg>
                                        <span class="wa-placeholder">Ketik pesan...</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"></path>
                                        </svg>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                    </div>
                                    <div class="wa-mic-btn">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                                            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Home bar -->
                                <div class="phone-home-indicator"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px dashed var(--border-subtle); margin: 24px 0;">

            <!-- 2. Template Rekap Owner -->
            <div>
                <label class="form-label" style="font-weight: 700; font-size: 14px;">2. Template Rekap Laporan untuk Owner / Finance</label>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">Pesan ringkasan harian daftar piutang jatuh tempo yang dikirim ke nomor tetap toko.</div>

                <div style="display: grid; grid-template-columns: minmax(320px, 1.2fr) 340px; gap: 24px; align-items: start;">
                    <div>
                        <textarea id="templateRecapText" name="template_recap" class="control-textarea form-control" rows="12" style="font-family: inherit; font-size: 13px; line-height: 1.6;" oninput="updateRecapPreview()">{{ $templateRecap->body }}</textarea>
                        
                        <div style="margin-top: 10px;">
                            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Klik Variabel untuk Menyisipkan:</span>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                                <button type="button" class="btn-chip" onclick="insertTag('templateRecapText', '{nama_toko}')">{nama_toko}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateRecapText', '{tanggal_sekarang}')">{tanggal_sekarang}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateRecapText', '{total_piutang}')">{total_piutang}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateRecapText', '{total_pelanggan}')">{total_pelanggan}</button>
                                <button type="button" class="btn-chip" onclick="insertTag('templateRecapText', '{daftar_pelanggan}')">{daftar_pelanggan}</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live WhatsApp Smartphone Preview Mockup (Rekap Owner) -->
                    <div class="phone-mockup-wrapper">
                        <div class="phone-device">
                            <!-- Side buttons -->
                            <div class="phone-btn phone-btn-volume-up"></div>
                            <div class="phone-btn phone-btn-volume-down"></div>
                            <div class="phone-btn phone-btn-power"></div>

                            <!-- Screen -->
                            <div class="phone-screen">
                                <!-- Dynamic Island -->
                                <div class="phone-island-bar">
                                    <div class="phone-island">
                                        <span class="phone-island-cam"></span>
                                    </div>
                                </div>

                                <!-- Status Bar -->
                                <div class="phone-status-bar">
                                    <span class="status-time">09:41</span>
                                    <div class="status-icons">
                                        <!-- Cellular -->
                                        <svg width="12" height="10" viewBox="0 0 12 10" fill="currentColor">
                                            <rect x="0" y="7" width="2" height="3" rx="0.5"/>
                                            <rect x="3" y="5" width="2" height="5" rx="0.5"/>
                                            <rect x="6" y="3" width="2" height="7" rx="0.5"/>
                                            <rect x="9" y="0" width="2" height="10" rx="0.5"/>
                                        </svg>
                                        <!-- Wifi -->
                                        <svg width="12" height="10" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 4C7.31 4 3.07 5.9 0 8.98L12 21 24 8.98C20.93 5.9 16.69 4 12 4zm0 4c3.48 0 6.64 1.35 9 3.55L12 19.5 3 11.55C5.36 9.35 8.52 8 12 8z"/>
                                        </svg>
                                        <!-- Battery -->
                                        <svg width="18" height="9" viewBox="0 0 24 12" fill="currentColor">
                                            <rect x="1" y="1" width="19" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
                                            <rect x="3" y="3" width="13" height="6" rx="1"/>
                                            <path d="M22 4.5v3a1.5 1.5 0 001.5-1.5 1.5 1.5 0 00-1.5-1.5z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- WhatsApp App Header -->
                                <div class="wa-app-header">
                                    <div class="wa-header-left">
                                        <svg class="wa-back-btn" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                        <div class="wa-avatar" style="background: #0284C7;">
                                            <span>RP</span>
                                        </div>
                                        <div class="wa-user-details">
                                            <div class="wa-user-name">Rekap Piutang Bot</div>
                                            <div class="wa-user-status">online</div>
                                        </div>
                                    </div>
                                    <div class="wa-header-actions">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1v-3.5l4 4v-11l-4 4z"/></svg>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.045 15.045 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1z"/></svg>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                                    </div>
                                </div>

                                <!-- WhatsApp Chat Canvas -->
                                <div class="wa-chat-container">
                                    <div class="wa-date-chip">HARI INI</div>

                                    <!-- WhatsApp Chat Bubble (Outgoing) -->
                                    <div class="wa-message-bubble">
                                        <div class="wa-message-text" id="previewRecapBox"></div>
                                        <div class="wa-message-meta">
                                            <span class="wa-msg-time">{{ now()->format('H:i') }}</span>
                                            <svg class="wa-ticks" width="14" height="10" viewBox="0 0 16 11" fill="none">
                                                <path d="M11.05 0.7L4.7 7.05L1.95 4.3L0.85 5.4L4.7 9.25L12.15 1.8L11.05 0.7Z" fill="#53BDEB"/>
                                                <path d="M15.15 0.7L8.8 7.05L7.9 6.15L6.8 7.25L8.8 9.25L16.25 1.8L15.15 0.7Z" fill="#53BDEB"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- WhatsApp Input Footer -->
                                <div class="wa-chat-footer">
                                    <div class="wa-input-pill">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                                        </svg>
                                        <span class="wa-placeholder">Ketik pesan...</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"></path>
                                        </svg>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8696A0" stroke-width="2">
                                            <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"></path>
                                            <circle cx="12" cy="13" r="4"></circle>
                                        </svg>
                                    </div>
                                    <div class="wa-mic-btn">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                                            <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Home bar -->
                                <div class="phone-home-indicator"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-icon btn-primary" data-tooltip="Simpan Semua Pengaturan & Template">
                    <x-icon name="save" />
                </button>
            </div>
        </div>
    </form>

    <style>
        .btn-chip {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-family: 'JetBrains Mono', monospace;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-chip:hover {
            background: #2F5BEA;
            border-color: #2F5BEA;
            color: #FFFFFF;
        }

        /* Phone Mockup Styling */
        .phone-mockup-wrapper {
            display: flex;
            justify-content: center;
            position: sticky;
            top: 20px;
        }

        .phone-device {
            width: 100%;
            max-width: 330px;
            background: #181B20;
            border-radius: 38px;
            padding: 9px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.22), 0 2px 6px rgba(0, 0, 0, 0.12);
            border: 2px solid #2B323D;
            position: relative;
            box-sizing: border-box;
        }

        .phone-btn {
            position: absolute;
            background: #2B323D;
            border-radius: 2px;
        }
        .phone-btn-volume-up {
            left: -4px;
            top: 80px;
            width: 3px;
            height: 34px;
        }
        .phone-btn-volume-down {
            left: -4px;
            top: 122px;
            width: 3px;
            height: 34px;
        }
        .phone-btn-power {
            right: -4px;
            top: 96px;
            width: 3px;
            height: 48px;
        }

        .phone-screen {
            border-radius: 30px;
            overflow: hidden;
            background-color: #EFEAE2;
            display: flex;
            flex-direction: column;
            min-height: 440px;
            position: relative;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .phone-island-bar {
            position: absolute;
            top: 6px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            z-index: 20;
            pointer-events: none;
        }
        .phone-island {
            width: 72px;
            height: 18px;
            background: #000000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 7px;
        }
        .phone-island-cam {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #101827;
            border: 1px solid #1F2937;
        }

        .phone-status-bar {
            background: #008069;
            color: #FFFFFF;
            padding: 7px 16px 3px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            font-weight: 600;
            position: relative;
            z-index: 10;
        }
        .phone-status-bar .status-icons {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .wa-app-header {
            background: #008069;
            color: #FFFFFF;
            padding: 6px 12px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }
        .wa-header-left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .wa-back-btn {
            cursor: pointer;
            opacity: 0.9;
        }
        .wa-avatar {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            background: #128C7E;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .wa-user-details {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .wa-user-name {
            font-size: 12.5px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.2;
        }
        .wa-user-status {
            font-size: 10px;
            opacity: 0.85;
            line-height: 1.2;
        }
        .wa-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0.9;
        }

        .wa-chat-container {
            background-color: #EFEAE2;
            background-image: 
                radial-gradient(#d3caba 0.75px, transparent 0.75px), 
                radial-gradient(#d3caba 0.75px, #EFEAE2 0.75px);
            background-size: 18px 18px;
            background-position: 0 0, 9px 9px;
            flex: 1;
            padding: 12px 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 280px;
            position: relative;
        }

        .wa-date-chip {
            align-self: center;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 9.5px;
            font-weight: 600;
            color: #54656F;
            box-shadow: 0 1px 1px rgba(11, 20, 26, 0.12);
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }

        .wa-message-bubble {
            align-self: flex-end;
            background: #D9FDD3;
            border-radius: 8px 8px 2px 8px;
            padding: 7px 9px 4px;
            max-width: 92%;
            box-shadow: 0 1px 1px rgba(11, 20, 26, 0.13);
            position: relative;
            word-break: break-word;
        }
        .wa-message-bubble::after {
            content: "";
            position: absolute;
            top: 0;
            right: -6px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 0 8px 8px;
            border-color: transparent transparent transparent #D9FDD3;
        }

        .wa-message-text {
            font-size: 12px;
            line-height: 1.45;
            color: #111B21;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .wa-message-text strong, .wa-message-text b {
            font-weight: 700;
            color: #0B141A;
        }
        .wa-message-text em, .wa-message-text i {
            font-style: italic;
        }

        .wa-message-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 3px;
            margin-top: 2px;
        }
        .wa-msg-time {
            font-size: 9.5px;
            color: #667781;
        }
        .wa-ticks {
            display: inline-block;
        }

        .wa-chat-footer {
            background: #F0F2F5;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        .wa-input-pill {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1;
            font-size: 11px;
            color: #8696A0;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
        }
        .wa-placeholder {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wa-mic-btn {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            background: #008069;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
        }

        .phone-home-indicator {
            width: 104px;
            height: 4px;
            background: #181B20;
            border-radius: 4px;
            margin: 6px auto 3px;
        }

        @media (max-width: 900px) {
            div[style*="grid-template-columns: minmax(320px, 1.2fr) 340px"] {
                grid-template-columns: 1fr !important;
            }
            .phone-mockup-wrapper {
                position: static;
                margin-top: 16px;
            }
        }
    </style>

    <script>
        function insertTag(textareaId, tag) {
            const textarea = document.getElementById(textareaId);
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + tag + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + tag.length;
            if (textareaId === 'templateCustomerText') updateCustomerPreview();
            if (textareaId === 'templateRecapText') updateRecapPreview();
        }

        function formatWhatsApp(str) {
            if (!str) return '';
            // Escape HTML
            let text = str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            // Bold *text*
            text = text.replace(/\*([^\*\n]+)\*/g, '<strong>$1</strong>');
            // Italic _text_
            text = text.replace(/_([^_\n]+)_/g, '<em>$1</em>');
            // Strike ~text~
            text = text.replace(/~([^~\n]+)~/g, '<del>$1</del>');
            // Linebreaks
            text = text.replace(/\n/g, '<br>');
            return text;
        }

        function updateCustomerPreview() {
            let val = document.getElementById('templateCustomerText').value;
            val = val.replace(/{nama_pelanggan}/g, 'Bpk. Ahmad Supardi')
                     .replace(/{nama_toko}/g, '{{ addslashes($connection->label ?? "Toko Maju Jaya") }}')
                     .replace(/{total_piutang}/g, 'Rp 1.450.000')
                     .replace(/{jumlah_nota}/g, '2')
                     .replace(/{tanggal_tempo}/g, '{{ now()->format("d M Y") }}');
            document.getElementById('previewCustomerBox').innerHTML = formatWhatsApp(val);
        }

        function updateRecapPreview() {
            let val = document.getElementById('templateRecapText').value;
            val = val.replace(/{nama_toko}/g, '{{ addslashes($connection->label ?? "Toko Maju Jaya") }}')
                     .replace(/{tanggal_sekarang}/g, '{{ now()->format("d/m/Y H:i") }}')
                     .replace(/{total_piutang}/g, 'Rp 18.750.000')
                     .replace(/{total_pelanggan}/g, '6')
                     .replace(/{daftar_pelanggan}/g, "1. Ahmad Supardi: Rp 1.450.000 (2 nota)\n2. Toko Berkah: Rp 3.200.000 (1 nota)\n3. CV Mandiri: Rp 5.100.000 (3 nota)");
            document.getElementById('previewRecapBox').innerHTML = formatWhatsApp(val);
        }

        function resetDefaultTemplates() {
            if (confirm('Kembalikan isi template pesan ke format standar sistem?')) {
                document.getElementById('templateCustomerText').value = "Halo Bapak/Ibu *{nama_pelanggan}*,\n\nKami dari *{nama_toko}* menginformasikan bahwa terdapat tagihan yang belum terselesaikan sebesar *{total_piutang}* ({jumlah_nota} nota belum lunas).\n\nMohon untuk melakukan konfirmasi atau penyelesaian pembayaran. Abaikan pesan ini jika sudah melakukan pembayaran. Terima kasih atas kerja samanya.";
                document.getElementById('templateRecapText').value = "*[REKAP PIUTANG JATUH TEMPO]*\nToko: *{nama_toko}*\nTanggal: {tanggal_sekarang}\n\nTotal Piutang Overdue: *{total_piutang}*\nTotal Pelanggan Menunggak: *{total_pelanggan} orang*\n\n{daftar_pelanggan}\n\nSilakan cek detail lengkap di aplikasi modsysv4.";
                updateCustomerPreview();
                updateRecapPreview();
            }
        }

        // Initialize previews on load
        document.addEventListener('DOMContentLoaded', () => {
            updateCustomerPreview();
            updateRecapPreview();
        });
    </script>

    <!-- Riwayat Pengiriman WA Terakhir -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Riwayat Pengiriman Pesan WhatsApp (Log)</span>
            <span style="font-size: 12px; color: var(--text-muted);">30 aktivitas pesan terakhir</span>
        </div>

        @if($history->isEmpty())
            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                Belum ada riwayat pesan yang dikirimkan melalui koneksi ini.
            </div>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Tujuan</th>
                            <th>Pesan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $msg)
                            <tr>
                                <td>{{ $msg->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <strong>{{ $msg->customer_id ? 'Pelanggan: ' . $msg->customer_id : 'Nomor Tetap' }}</strong>
                                    <div><code>+{{ $msg->phone }}</code></div>
                                </td>
                                <td><small style="color: var(--text-muted);">{{ Str::limit($msg->body, 60) }}</small></td>
                                <td>
                                    @if($msg->status === 'sent')
                                        <span class="badge badge-success">Terkirim</span>
                                    @else
                                        <span class="badge badge-danger" title="{{ $msg->error }}">Gagal</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
