<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class V8BukuBesarController extends Controller
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
     * Web UI: Dashboard Modul Cek Buku Besar (Bot Tele)
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

        // Pengaturan bot untuk informasi integrasi telegram
        $setting = BotTeleSetting::where('account_id', $account->id)->first();

        // Ambil daftar akun paymenttype dari Acosys V8 beserta saldo realtime
        $paymentAccounts = [];
        $connectionError = null;

        if ($v8Connection) {
            try {
                $paymentAccounts = $this->connector->getPaymentAccountsWithBalance($v8Connection);
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data dari database Acosys V8: " . $e->getMessage();
            }
        }

        $totalSaldoKasBank = array_sum(array_column($paymentAccounts, 'saldo'));

        return view('modules.v8_buku_besar.index', compact(
            'account',
            'v8Connection',
            'setting',
            'paymentAccounts',
            'connectionError',
            'totalSaldoKasBank'
        ));
    }
}
