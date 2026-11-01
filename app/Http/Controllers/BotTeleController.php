<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Models\BotTeleSession;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotTeleController extends Controller
{
    protected AcosysConnector $connector;

    public function __construct(AcosysConnector $connector)
    {
        $this->connector = $connector;
    }

    protected function getActiveAccount()
    {
        return Auth::user()?->getActiveAccount();
    }

    /**
     * Web UI: Dashboard Modul Bot Tele
     */
    public function index()
    {
        $account = $this->getActiveAccount();
        if (!$account) {
            return redirect()->route('dashboard');
        }

        // Cari koneksi Acosys V8 milik akun
        $v8Connection = $account->connections()->where('acosys_version', 'v8')->first();
        if (!$v8Connection) {
            $v8Connection = $account->connections()->first();
        }

        // Ambil atau buat pengaturan bot
        $setting = BotTeleSetting::firstOrCreate(
            ['account_id' => $account->id],
            [
                'connection_id' => $v8Connection?->id,
                'is_active' => true,
            ]
        );

        if (!$setting->connection_id && $v8Connection) {
            $setting->update(['connection_id' => $v8Connection->id]);
        }

        // Ambil daftar akun paymenttype dari Acosys V8
        $paymentAccounts = [];
        $connectionError = null;

        if ($v8Connection) {
            try {
                $paymentAccounts = $this->connector->getPaymentAccountsWithBalance($v8Connection);
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data dari database Acosys V8: " . $e->getMessage();
            }
        }

        // Sesi aktif Telegram
        $activeSessions = BotTeleSession::where('account_id', $account->id)->latest('last_active_at')->get();

        // Generate webhook URL
        $secret = md5($account->id . '_bot_tele_v8_salt');
        $webhookUrl = url("/api/telegram/webhook/{$secret}");

        return view('modules.bot_tele.index', compact(
            'account',
            'v8Connection',
            'setting',
            'paymentAccounts',
            'connectionError',
            'activeSessions',
            'webhookUrl'
        ));
    }

    /**
     * Simpan Pengaturan Bot & Pasang Webhook Otomatis
     */
    public function saveSettings(Request $request)
    {
        $account = $this->getActiveAccount();
        $setting = BotTeleSetting::firstOrCreate(['account_id' => $account->id]);

        $validated = $request->validate([
            'bot_token' => 'nullable|string|max:255',
            'connection_id' => 'nullable|exists:acosys_connections,id',
            'is_active' => 'nullable|boolean',
        ]);

        $tokenInput = trim($validated['bot_token'] ?? '');
        $isTokenChanged = !empty($tokenInput) && ($tokenInput !== $setting->decrypted_bot_token);

        if ($isTokenChanged) {
            $setting->bot_token = $tokenInput;
        }

        if (isset($validated['connection_id'])) {
            $setting->connection_id = $validated['connection_id'];
        }
        $setting->is_active = $request->boolean('is_active', true);
        $setting->save();

        // Jika token diisi, otomatis verifikasi bot via getMe dan daftarkan webhook
        $effectiveToken = $setting->decrypted_bot_token;
        if (!empty($effectiveToken)) {
            $testMe = $setting->testGetMe($effectiveToken);

            if ($testMe['status']) {
                $setting->bot_username = $testMe['bot_username'] ?? '';
                $setting->last_test_ok = true;
                $setting->last_test_at = now();
                $setting->last_test_message = $testMe['message'];
                $setting->save();

                // Pasang webhook otomatis ke Telegram
                $secret = md5($account->id . '_bot_tele_v8_salt');
                $webhookUrl = url("/api/telegram/webhook/{$secret}");

                try {
                    $setHookResp = Http::withoutVerifying()
                        ->timeout(10)
                        ->post("https://api.telegram.org/bot{$effectiveToken}/setWebhook", [
                            'url' => $webhookUrl,
                            'drop_pending_updates' => true,
                        ]);

                    $hookJson = $setHookResp->json();
                    if (!empty($hookJson['ok'])) {
                        return back()->with('success', "Pengaturan tersimpan! Bot @{$setting->bot_username} berhasil terverifikasi & Webhook Telegram aktif.");
                    } else {
                        return back()->with('warning', "Bot @{$setting->bot_username} valid, namun pemasangan webhook gagal: " . ($hookJson['description'] ?? ''));
                    }
                } catch (Throwable $e) {
                    return back()->with('success', "Bot @{$setting->bot_username} tersimpan.");
                }
            } else {
                $setting->last_test_ok = false;
                $setting->last_test_at = now();
                $setting->last_test_message = $testMe['message'];
                $setting->save();

                return back()->with('error', "Gagal menghubungkan bot: {$testMe['message']}");
            }
        }

        return back()->with('success', 'Pengaturan Bot Tele berhasil disimpan.');
    }

    /**
     * Uji Kirim Pesan ke Telegram
     */
    public function testNotification(Request $request)
    {
        $account = $this->getActiveAccount();
        $setting = BotTeleSetting::where('account_id', $account->id)->first();

        if (!$setting || empty($setting->decrypted_bot_token)) {
            return back()->with('error', 'Bot Token belum diisi.');
        }

        $chatId = $request->input('chat_id');
        if (empty($chatId)) {
            return back()->with('error', 'Masukkan Chat ID Telegram tujuan.');
        }

        $message = "*Tes Pesan dari modsysv4 (Acosys V8)*\n\nKoneksi Bot Telegram Anda berhasil berfungsi dengan baik! Siap digunakan untuk pengecekan saldo buku besar.";
        $res = $setting->sendMessage($message, $chatId);

        if ($res['status']) {
            return back()->with('success', 'Pesan tes berhasil terkirim ke Telegram!');
        }

        return back()->with('error', $res['message']);
    }

    /**
     * PUBLIC WEBHOOK TELEGRAM
     * Dipanggil secara realtime oleh Telegram Bot API saat ada pesan/interaksi
     */
    public function webhook(Request $request, $secret)
    {
        // Temukan setting berdasarkan secret
        $allSettings = BotTeleSetting::with(['account', 'connection'])->get();
        $setting = null;

        foreach ($allSettings as $s) {
            if (md5($s->account_id . '_bot_tele_v8_salt') === $secret) {
                $setting = $s;
                break;
            }
        }

        if (!$setting || !$setting->is_active) {
            return response()->json(['ok' => true]);
        }

        $connection = $setting->connection;
        if (!$connection) {
            return response()->json(['ok' => true]);
        }

        $update = $request->all();
        $token = $setting->decrypted_bot_token;

        // 1. Tangani Pesan Masuk (Incoming Message)
        if (!empty($update['message'])) {
            $message = $update['message'];
            $chatId = (string)($message['chat']['id'] ?? '');
            $messageId = $message['message_id'] ?? null;
            $text = trim($message['text'] ?? '');
            $senderName = $message['from']['first_name'] ?? 'Pengguna';

            if (empty($chatId) || empty($text)) {
                return response()->json(['ok' => true]);
            }

            // Cek Sesi Login pengguna
            $session = BotTeleSession::where('account_id', $setting->account_id)
                ->where('chat_id', $chatId)
                ->first();

            // A. Perintah /start
            if ($text === '/start') {
                if ($session) {
                    $reply = "Halo, *{$session->acosys_user_name}*!\n\n"
                        . "Akun Telegram Anda terhubung ke database kasir *{$connection->label}*.\n\n"
                        . "Silakan gunakan tombol menu di bawah untuk mengakses fitur kasir:";
                    $this->sendReplyMenu($token, $chatId, $reply, $setting->account);
                } else {
                    $reply = "*Selamat datang di Bot Acosys V8*\n\n"
                        . "Untuk mengakses informasi saldo kasir dan buku besar, silakan login dengan akun kasir/staf Acosys Anda.\n\n"
                        . "*Format Login:*\n`username password`\n\n"
                        . "_Contoh:_\n`admin 123456`\n\n"
                        . "_Pesan kredensial Anda akan otomatis dihapus oleh bot demi keamanan._";
                    $this->sendTelegramMessage($token, $chatId, $reply);
                }
                return response()->json(['ok' => true]);
            }

            // B. Perintah /logout atau Tombol "Logout"
            if ($text === '/logout' || str_contains($text, 'Logout')) {
                if ($session) {
                    $session->delete();
                    $reply = "*Logout Berhasil!*\nSesi Anda telah diakhiri. Ketik `/start` kapan saja untuk login kembali.";
                    $this->sendTelegramMessage($token, $chatId, $reply, ['remove_keyboard' => true]);
                } else {
                    $this->sendTelegramMessage($token, $chatId, "Anda belum login. Ketik `/start` untuk login.");
                }
                return response()->json(['ok' => true]);
            }

            // C. Tombol "Cek Saldo Buku Besar" atau command /saldo
            if (str_contains($text, 'Saldo Buku Besar') || $text === '/saldo' || str_starts_with($text, '/saldo')) {
                if (!$session) {
                    $reply = "*Akses Ditolak!*\nAnda harus login terlebih dahulu.\n\nKetik format:\n`username password`";
                    $this->sendTelegramMessage($token, $chatId, $reply);
                    return response()->json(['ok' => true]);
                }

                // Validasi Lisensi Modul v8_buku_besar pada akun toko
                $account = $setting->account;
                if (!$account || !$account->hasActiveModule('v8_buku_besar')) {
                    $reply = "*Modul Belum Aktif!*\n\n"
                        . "Fitur *Cek Saldo Buku Besar* belum diaktifkan atau masa langganan toko telah berakhir.\n\n"
                        . "Silakan hubungi pemilik toko untuk mengaktifkan modul ini di panel web modsysv4.";
                    $this->sendTelegramMessage($token, $chatId, $reply);
                    return response()->json(['ok' => true]);
                }

                // Ambil payment accounts dari Acosys V8 (tabel paymenttype)
                $paymentAccounts = $this->connector->getPaymentAccountsWithBalance($connection);

                if (empty($paymentAccounts)) {
                    $reply = "Peringatan: Tidak ditemukan akun pembayaran aktif di tabel `paymenttype` database Acosys V8.";
                    $this->sendTelegramMessage($token, $chatId, $reply);
                    return response()->json(['ok' => true]);
                }

                // Bangun Inline Keyboard
                $inlineKeyboard = [];
                foreach ($paymentAccounts as $acc) {
                    $inlineKeyboard[] = [
                        [
                            'text' => "[{$acc['account_id']}] {$acc['account_name']} ({$acc['payment_types']})",
                            'callback_data' => "saldo:{$acc['account_id']}",
                        ]
                    ];
                }

                $reply = "*PILIH AKUN BUKU BESAR*\n\n"
                    . "Berikut adalah daftar akun pembayaran kasir (Kas & Bank) dari database *{$connection->label}*.\n"
                    . "Klik akun di bawah untuk melihat saldo realtime:";

                $this->sendTelegramMessage($token, $chatId, $reply, [
                    'inline_keyboard' => $inlineKeyboard,
                ]);
                return response()->json(['ok' => true]);
            }

            // D. Proses Login (Input Teks "username password" atau "/login username password")
            $loginText = str_starts_with($text, '/login ') ? substr($text, 7) : $text;
            $parts = preg_split('/\s+/', trim($loginText), 2);

            if (count($parts) === 2 && !$session) {
                $inputUser = trim($parts[0]);
                $inputPass = trim($parts[1]);

                // Hapus pesan password dari riwayat chat demi keamanan (Opsi A)
                if ($messageId) {
                    $this->deleteTelegramMessage($token, $chatId, $messageId);
                }

                // Verifikasi ke database Acosys V8 tabel users
                $authResult = $this->connector->authenticateV8User($connection, $inputUser, $inputPass);

                if ($authResult['status']) {
                    $user = $authResult['user'];

                    // Simpan sesi login
                    BotTeleSession::updateOrCreate(
                        ['account_id' => $setting->account_id, 'chat_id' => $chatId],
                        [
                            'acosys_user_id' => $user['id'],
                            'acosys_user_name' => $user['name'],
                            'acosys_user_role' => $user['roles'],
                            'last_active_at' => now(),
                        ]
                    );

                    $reply = "*Login Berhasil!*\n\n"
                        . "Selamat datang, *{$user['name']}* ({$user['roles']})!\n"
                        . "Terhubung ke database: *{$connection->label}*\n\n"
                        . "Silakan klik menu di bawah untuk mulai menggunakan bot:";

                    $this->sendReplyMenu($token, $chatId, $reply, $setting->account);
                } else {
                    $reply = "*Login Gagal!*\n\n"
                        . "{$authResult['message']}\n\n"
                        . "Silakan coba lagi dengan format:\n`username password`";
                    $this->sendTelegramMessage($token, $chatId, $reply);
                }
                return response()->json(['ok' => true]);
            }

            // Jika pesan tidak dikenali
            if ($session) {
                $reply = "Pilihan tidak dikenali. Silakan gunakan tombol di bawah:";
                $this->sendReplyMenu($token, $chatId, $reply, $setting->account);
            } else {
                $reply = "Ketik `/start` untuk memulai atau login dengan format:\n`username password`";
                $this->sendTelegramMessage($token, $chatId, $reply);
            }
        }

        // 2. Tangani Callback Query (Klik Tombol Inline Keyboard Akun)
        if (!empty($update['callback_query'])) {
            $cq = $update['callback_query'];
            $cqId = $cq['id'];
            $chatId = (string)($cq['message']['chat']['id'] ?? '');
            $data = $cq['data'] ?? '';

            // Acknowledge callback
            $this->answerCallbackQuery($token, $cqId);

            if (str_starts_with($data, 'saldo:')) {
                $accountId = substr($data, 6);

                // Periksa sesi
                $session = BotTeleSession::where('account_id', $setting->account_id)
                    ->where('chat_id', $chatId)
                    ->first();

                if (!$session) {
                    $this->sendTelegramMessage($token, $chatId, "Peringatan: Sesi Anda telah berakhir. Silakan login kembali dengan `/start`.");
                    return response()->json(['ok' => true]);
                }

                // Validasi Lisensi Modul v8_buku_besar
                $account = $setting->account;
                if (!$account || !$account->hasActiveModule('v8_buku_besar')) {
                    $this->sendTelegramMessage($token, $chatId, "Peringatan: Lisensi modul Cek Buku Besar toko Anda belum aktif.");
                    return response()->json(['ok' => true]);
                }

                // Ambil saldo akun spesifik dari journaltrans
                $balInfo = $this->connector->getSingleAccountBalance($connection, $accountId);

                if ($balInfo) {
                    $receipt = "*INFORMASI SALDO BUKU BESAR*\n"
                        . "━━━━━━━━━━━━━━━━━━━━\n"
                        . "Akun: `{$balInfo['account_id']}` — *{$balInfo['account_name']}*\n"
                        . "━━━━━━━━━━━━━━━━━━━━\n"
                        . "Total Debit  : Rp " . number_format($balInfo['total_debit'], 0, ',', '.') . "\n"
                        . "Total Kredit : Rp " . number_format($balInfo['total_credit'], 0, ',', '.') . "\n"
                        . "━━━━━━━━━━━━━━━━━━━━\n"
                        . "*Saldo Terakhir: {$balInfo['formatted_saldo']}*\n"
                        . "━━━━━━━━━━━━━━━━━━━━\n"
                        . "Waktu: " . now()->format('d/m/Y H:i') . " WIB\n"
                        . "Pengguna: {$session->acosys_user_name} ({$session->acosys_user_role})";

                    $this->sendTelegramMessage($token, $chatId, $receipt);
                } else {
                    $this->sendTelegramMessage($token, $chatId, "Gagal memuat saldo akun `{$accountId}`.");
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Helper: Kirim Pesan dengan Reply Keyboard Menu Utama Dinamis sesuai Lisensi Modul Toko
     */
    protected function sendReplyMenu(string $token, string $chatId, string $text, $account = null)
    {
        $keyboardRows = [];

        // Modul 1: Cek Buku Besar (v8_buku_besar)
        if ($account && $account->hasActiveModule('v8_buku_besar')) {
            $keyboardRows[] = [
                ['text' => 'Cek Saldo Buku Besar'],
            ];
        }

        // Tombol Logout selalu ada di bagian bawah
        $keyboardRows[] = [
            ['text' => 'Logout'],
        ];

        $keyboard = [
            'keyboard' => $keyboardRows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];

        return $this->sendTelegramMessage($token, $chatId, $text, $keyboard);
    }

    /**
     * Helper: Kirim Pesan Telegram
     */
    protected function sendTelegramMessage(string $token, string $chatId, string $text, array $replyMarkup = [])
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ];

            if (!empty($replyMarkup)) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            return Http::withoutVerifying()
                ->timeout(8)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload)
                ->json();
        } catch (Throwable $e) {
            Log::error('Telegram sendMessage error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper: Hapus Pesan Telegram (untuk keamanan password)
     */
    protected function deleteTelegramMessage(string $token, string $chatId, int $messageId)
    {
        try {
            Http::withoutVerifying()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/deleteMessage", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
        } catch (Throwable $e) {}
    }

    /**
     * Helper: Answer Callback Query
     */
    protected function answerCallbackQuery(string $token, string $callbackQueryId)
    {
        try {
            Http::withoutVerifying()
                ->timeout(5)
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", [
                    'callback_query_id' => $callbackQueryId,
                ]);
        } catch (Throwable $e) {}
    }
}
