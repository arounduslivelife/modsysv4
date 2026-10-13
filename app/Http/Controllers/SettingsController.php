<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\AccountMember;
use App\Models\User;
use App\Models\WaDevice;
use App\Services\AcosysConnector;
use App\Services\WaGatewayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        $conn->update([
            'last_test_ok' => $test['status'],
            'last_test_at' => now(),
            'test_message' => $test['message'],
        ]);

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
}
