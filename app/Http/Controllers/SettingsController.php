<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\AccountMember;
use App\Models\BotTeleSetting;
use App\Models\BotTeleSession;
use App\Models\User;
use App\Models\WaDevice;
use App\Services\AcosysConnector;
use App\Services\WaGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SettingsController extends Controller
{
    protected AcosysConnector $connector;
    protected WaGatewayClient $waClient;

    public function __construct(AcosysConnector $connector, WaGatewayClient $waClient)
    {
        $this->connector = $connector;
        $this->waClient = $waClient;
    }

    protected function getActiveAccount()
    {
        return Auth::user()?->getActiveAccount();
    }

    /* ================= 1. KONEKSI ACOSYS ================= */

    public function connections()
    {
        $account = $this->getActiveAccount();
        $connections = $account->connections()->latest()->get();
        return view('settings.connections', compact('account', 'connections'));
    }

    public function storeConnection(Request $request)
    {
        $account = $this->getActiveAccount();

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'acosys_version' => 'nullable|string|in:v4,v8',
            'host' => 'required|string|max:255',
            'port' => 'required|numeric',
            'database' => 'required|string|max:100',
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ]);

        // Auto-test before saving
        $testResult = $this->connector->testConnection(
            $validated['host'],
            (int)$validated['port'],
            $validated['database'],
            $validated['username'],
            $validated['password']
        );

        $validated['last_test_ok'] = $testResult['status'];
        $validated['last_test_at'] = now();
        $validated['test_message'] = $testResult['message'];

        // Auto-detect version if detected or fallback
        if (!empty($testResult['detected_version'])) {
            $validated['acosys_version'] = $validated['acosys_version'] ?: $testResult['detected_version'];
        } else {
            $validated['acosys_version'] = $validated['acosys_version'] ?: 'v4';
        }

        $conn = $account->connections()->create($validated);

        if (!session('active_connection_id')) {
            session(['active_connection_id' => $conn->id]);
        }

        if ($testResult['status']) {
            return back()->with('success', "Koneksi berhasil ditambahkan dan teruji: {$testResult['message']}");
        } else {
            return back()->with('error', "Koneksi disimpan, tetapi tes koneksi gagal: {$testResult['message']}");
        }
    }

    public function testConnection(Request $request, $id)
    {
        $account = $this->getActiveAccount();
        $conn = $account->connections()->findOrFail($id);

        $test = $this->connector->testConnection(
            $conn->host,
            $conn->port,
            $conn->database,
            $conn->username,
            $conn->decrypted_password
        );

        $updateData = [
            'last_test_ok' => $test['status'],
            'last_test_at' => now(),
            'test_message' => $test['message'],
        ];

        if (!empty($test['detected_version'])) {
            $updateData['acosys_version'] = $test['detected_version'];
        }

        $conn->update($updateData);

        if ($request->wantsJson()) {
            return response()->json($test);
        }

        return back()->with($test['status'] ? 'success' : 'error', $test['message']);
    }

    public function deleteConnection($id)
    {
        $account = $this->getActiveAccount();
        $conn = $account->connections()->findOrFail($id);
        $conn->delete();

        if (session('active_connection_id') == $id) {
            session()->forget('active_connection_id');
        }

        return back()->with('success', 'Koneksi database berhasil dihapus.');
    }

    /* ================= 2. STAF & ANGGOTA ================= */

    public function staff()
    {
        $account = $this->getActiveAccount();
        $members = $account->members()->with('user')->get();
        return view('settings.staff', compact('account', 'members'));
    }

    public function inviteStaff(Request $request)
    {
        $account = $this->getActiveAccount();
        $email = strtolower(trim($request->input('email')));

        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:staff,owner',
        ]);

        // Find or create placeholder user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => explode('@', $email)[0],
                'status' => 'active',
            ]
        );

        AccountMember::firstOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $request->input('role', 'staff'),
                'status' => 'active',
            ]
        );

        return back()->with('success', "Staf {$email} berhasil ditambahkan ke akun usaha ini.");
    }

    public function removeStaff($id)
    {
        $account = $this->getActiveAccount();
        $member = $account->members()->findOrFail($id);

        if ($member->user_id == Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus diri sendiri dari akun usaha.');
        }

        $member->delete();
        return back()->with('success', 'Akses staf berhasil dicabut.');
    }

    /* ================= 3. WA GATEWAY DEVICE ================= */

    public function waDevice()
    {
        $account = $this->getActiveAccount();
        $devices = $account->waDevices;
        return view('settings.wa_device', compact('account', 'devices'));
    }

    public function storeWaDevice(Request $request)
    {
        $account = $this->getActiveAccount();

        $label = $request->input('label', 'WhatsApp Toko');
        $sessionKey = 'acc_' . $account->id . '_' . Str::random(8);

        $device = $account->waDevices()->create([
            'label' => $label,
            'session_key' => $sessionKey,
            'status' => 'disconnected',
        ]);

        // Initialize session on gateway
        $this->waClient->createSession($sessionKey);

        return back()->with('success', "Perangkat WhatsApp berhasil didaftarkan. Silakan klik tombol QR untuk scan.");
    }

    public function getWaDeviceQr($id)
    {
        $account = $this->getActiveAccount();
        $device = $account->waDevices()->findOrFail($id);

        $qrData = $this->waClient->getQr($device->session_key);

        if (!empty($qrData['phone'])) {
            $device->update([
                'status' => 'connected',
                'phone' => $qrData['phone'],
                'connected_at' => now(),
            ]);
        }

        return response()->json($qrData);
    }

    public function deleteWaDevice($id)
    {
        $account = $this->getActiveAccount();
        $device = $account->waDevices()->findOrFail($id);

        $this->waClient->deleteSession($device->session_key);
        $device->delete();

        return back()->with('success', 'Perangkat WhatsApp berhasil dihapus.');
    }

    /* ================= 4. BOT TELE V8 ================= */

    public function botTele()
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

        // Sesi aktif Telegram
        $activeSessions = BotTeleSession::where('account_id', $account->id)->latest('last_active_at')->get();

        // Webhook URL
        $secret = md5($account->id . '_bot_tele_v8_salt');
        $webhookUrl = url("/api/telegram/webhook/{$secret}");

        return view('settings.bot_tele', compact(
            'account',
            'v8Connection',
            'setting',
            'activeSessions',
            'webhookUrl'
        ));
    }

    public function saveBotTele(Request $request)
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

        return back()->with('success', 'Pengaturan Bot Tele V8 berhasil disimpan.');
    }

    public function testBotTele(Request $request)
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

        $message = "*Tes Pesan Konfigurasi dari modsysv4 (Acosys V8)*\n\nKoneksi Bot Telegram Anda berhasil berfungsi dengan baik! Siap melayani modul-modul Acosys V8.";
        $res = $setting->sendMessage($message, $chatId);

        if ($res['status']) {
            return back()->with('success', 'Pesan tes berhasil terkirim ke Telegram!');
        }

        return back()->with('error', $res['message']);
    }
}
