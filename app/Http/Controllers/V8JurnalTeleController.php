<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class V8JurnalTeleController extends Controller
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
     * Web UI: Dashboard Modul Jurnal Tele (Acosys V8)
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

        $recentJournals = [];
        $divisions = [];
        $connectionError = null;

        if ($v8Connection) {
            try {
                $recentJournals = $this->connector->getRecentJournals($v8Connection, 30);
                $divisions = $this->connector->getDivisions($v8Connection);
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data dari database Acosys V8: " . $e->getMessage();
            }
        }

        // Statistik hari ini
        $todayDate = date('Y-m-d');
        $todayJournals = array_filter($recentJournals, fn($j) => $j['jtdate'] === $todayDate);
        $todayCount = count($todayJournals);
        $todayTotal = array_sum(array_column($todayJournals, 'journalvalue'));

        return view('modules.v8_jurnal_tele.index', compact(
            'account',
            'v8Connection',
            'setting',
            'recentJournals',
            'divisions',
            'connectionError',
            'todayCount',
            'todayTotal'
        ));
    }
}
