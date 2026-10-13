<?php

namespace App\Console\Commands;

use App\Models\ReminderContact;
use App\Models\ReminderSetting;
use App\Models\WaMessage;
use App\Services\AcosysConnector;
use App\Services\WaGatewayClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SendPengingatPiutangCommand extends Command
{
    protected $signature = 'reminder:send-piutang {--force : Paksa kirim abaikan pengecekan jam kirim}';
    protected $description = 'Kirim notifikasi pengingat piutang otomatis via WhatsApp Gateway';

    public function handle(AcosysConnector $connector, WaGatewayClient $waClient)
    {
        $this->info('[' . now()->toDateTimeString() . '] Memulai pemrosesan pengingat piutang otomatis...');
        $isForce = $this->option('force');

        // Ambil semua pengaturan pengingat yang aktif
        $settings = ReminderSetting::where('enabled', true)->with('connection.account')->get();

        if ($settings->isEmpty()) {
            $this->comment('Tidak ada toko yang mengaktifkan pengingat piutang otomatis.');
            return 0;
        }

        $currentTime = now()->format('H:i');

        foreach ($settings as $setting) {
            $connection = $setting->connection;
            $account = $connection?->account;

            if (!$connection || !$account) {
                continue;
            }

            // Cek jam kirim (kecuali dipaksa dengan flag --force)
            if (!$isForce) {
                $sendTime = Carbon::parse($setting->send_time)->format('H:i');
                // Toleransi jendela waktu 30 menit
                $diffInMinutes = abs(now()->diffInMinutes(Carbon::parse($setting->send_time), false));
                if ($diffInMinutes > 15) {
                    $this->line("Melewati {$connection->label}: Jadwal kirim jam {$sendTime}, waktu sekarang {$currentTime}.");
                    continue;
                }
            }

            $this->info("Memproses toko: {$connection->label} (Akun ID: {$account->id})...");

            // Cari WhatsApp device yang terhubung
            $device = null;
            if ($setting->device_id) {
                $device = $account->waDevices()->where('id', $setting->device_id)->where('status', 'connected')->first();
            }
            if (!$device) {
                $device = $account->waDevices()->where('status', 'connected')->first();
            }

            if (!$device) {
                $this->warn("Toko {$connection->label} tidak memiliki WhatsApp sender yang aktif/online.");
                continue;
            }

            // Ambil nama perusahaan toko
            try {
                $companyInfo = $connector->getCompanyInfo($connection);
                $companyName = $companyInfo['name'] ?? $connection->label;
            } catch (Throwable $e) {
                $companyName = $connection->label;
            }

            // Ambil daftar piutang dari database Acosys
            try {
                $receivables = $connector->getReceivables($connection, ['overdue_only' => true]);
            } catch (Throwable $e) {
                $this->error("Gagal mengambil data piutang dari {$connection->label}: " . $e->getMessage());
                continue;
            }

            if (empty($receivables)) {
                $this->info("Tidak ada piutang jatuh tempo di {$connection->label}.");
                continue;
            }

            $recipientMode = $setting->recipient_mode ?? 'both'; // customer, fixed, both
            $fixedNumbers = array_filter(array_map('trim', explode(',', $setting->fixed_numbers ?? '')));
            $sentCount = 0;

            // 1. Kirim ke Pelanggan (jika mode customer atau both)
            if (in_array($recipientMode, ['customer', 'both'])) {
                foreach ($receivables as $r) {
                    if (!empty($r['opt_out'])) {
                        continue;
                    }

                    $phone = $r['phone'];
                    if (empty($phone) || strlen($phone) < 8) {
                        continue;
                    }

                    // Cek apakah sudah pernah dikirim hari ini agar tidak spam
                    $alreadySentToday = WaMessage::where('connection_id', $connection->id)
                        ->where('customer_id', $r['customer_id'])
                        ->whereDate('sent_at', today())
                        ->exists();

                    if ($alreadySentToday && !$isForce) {
                        continue;
                    }

                    // Template pelanggan
                    $custTpl = \App\Models\ReminderTemplate::where('account_id', $account->id)->where('type', 'customer')->first();
                    $defaultCustBody = "Halo Bapak/Ibu *{nama_pelanggan}*,\n\nKami dari *{nama_toko}* menginformasikan pengingat tagihan belum lunas sebesar *{total_piutang}* ({jumlah_nota} nota telah jatuh tempo).\n\nMohon untuk melakukan konfirmasi atau penyelesaian pembayaran. Abaikan pesan ini jika sudah melakukan pembayaran. Terima kasih.";
                    $templateCustBody = !empty($custTpl?->body) ? $custTpl->body : $defaultCustBody;

                    $amountStr = 'Rp ' . number_format((float)$r['total_piutang'], 0, ',', '.');
                    $body = str_replace([
                        '{nama_pelanggan}',
                        '{nama_toko}',
                        '{total_piutang}',
                        '{jumlah_nota}',
                        '{tanggal_tempo}',
                        '{tanggal_sekarang}',
                    ], [
                        $r['customer_name'],
                        $companyName,
                        $amountStr,
                        $r['overdue_count'] ?? '1',
                        $r['earliest_due_date'] ?? '-',
                        now()->format('d/m/Y H:i'),
                    ], $templateCustBody);

                    $sendResult = $waClient->sendMessage($device->session_key, $phone, $body);

                    WaMessage::create([
                        'account_id' => $account->id,
                        'connection_id' => $connection->id,
                        'device_id' => $device->id,
                        'customer_id' => $r['customer_id'],
                        'phone' => $phone,
                        'body' => $body,
                        'status' => $sendResult['status'] ? 'sent' : 'failed',
                        'error' => $sendResult['status'] ? null : ($sendResult['message'] ?? 'Error'),
                        'sent_at' => $sendResult['status'] ? now() : null,
                    ]);

                    if ($sendResult['status']) {
                        $sentCount++;
                        $this->line(" -> Notifikasi terkirim ke pelanggan: {$r['customer_name']} ({$phone})");
                    }

                    // Beri jeda 3-5 detik antar pelanggan agar aman dari antispam WhatsApp
                    sleep(3);
                }
            }

            // 2. Kirim Rekap ke Nomor Tetap / Owner (jika mode fixed atau both)
            if (in_array($recipientMode, ['fixed', 'both']) && !empty($fixedNumbers)) {
                $totalNominal = array_sum(array_column($receivables, 'total_piutang'));
                $totalCust = count($receivables);
                $nominalStr = 'Rp ' . number_format($totalNominal, 0, ',', '.');

                $summaryBody = "*[REKAP PIUTANG JATUH TEMPO]*\n"
                             . "Toko: *{$companyName}*\n"
                             . "Tanggal: " . now()->format('d M Y H:i') . "\n\n"
                             . "Total Piutang Overdue: *{$nominalStr}*\n"
                             . "Total Pelanggan Menunggak: *{$totalCust} orang*\n\n"
                             . "Daftar Teratas:\n";

                $top5 = array_slice($receivables, 0, 5);
                foreach ($top5 as $idx => $t) {
                    $tNominal = 'Rp ' . number_format($t['total_piutang'], 0, ',', '.');
                    $summaryBody .= ($idx + 1) . ". {$t['customer_name']}: {$tNominal} ({$t['overdue_count']} nota)\n";
                }

                if ($totalCust > 5) {
                    $summaryBody .= "... dan " . ($totalCust - 5) . " pelanggan lainnya.\n";
                }

                $summaryBody .= "\nSilakan cek detail lengkap di aplikasi modsysv4.";

                foreach ($fixedNumbers as $fixedPhone) {
                    $sendResult = $waClient->sendMessage($device->session_key, $fixedPhone, $summaryBody);

                    WaMessage::create([
                        'account_id' => $account->id,
                        'connection_id' => $connection->id,
                        'device_id' => $device->id,
                        'customer_id' => null,
                        'phone' => $fixedPhone,
                        'body' => $summaryBody,
                        'status' => $sendResult['status'] ? 'sent' : 'failed',
                        'error' => $sendResult['status'] ? null : ($sendResult['message'] ?? 'Error'),
                        'sent_at' => $sendResult['status'] ? now() : null,
                    ]);

                    if ($sendResult['status']) {
                        $this->line(" -> Rekap harian terkirim ke Owner: {$fixedPhone}");
                    }
                    sleep(2);
                }
            }

            $this->info("Selesai memproses {$connection->label}. Total pesan terkirim: {$sentCount}");
        }

        $this->info('Semua pengingat piutang selesai diproses.');
        return 0;
    }
}
