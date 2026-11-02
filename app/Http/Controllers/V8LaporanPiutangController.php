<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class V8LaporanPiutangController extends Controller
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
     * Web UI: Dashboard Modul Laporan Piutang (Acosys V8)
     */
    public function index(Request $request)
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

        $setting = BotTeleSetting::where('account_id', $account->id)->first();

        $searchQuery = trim($request->input('q', ''));
        $selectedCustomerId = trim($request->input('customer_id', ''));

        $summary = ['total_customers' => 0, 'total_receivable' => 0, 'formatted_total' => 'Rp 0'];
        $topDebtors = [];
        $searchResults = [];
        $customerDetail = null;
        $connectionError = null;

        if ($v8Connection) {
            try {
                $summary = $this->connector->getReceivableSummary($v8Connection);

                if ($selectedCustomerId !== '') {
                    $customerDetail = $this->connector->getCustomerReceivableDetail($v8Connection, $selectedCustomerId);
                }

                if ($searchQuery !== '') {
                    $searchResults = $this->connector->searchCustomerReceivables($v8Connection, $searchQuery, 20);
                } else {
                    $topDebtors = $this->connector->getTopDebtors($v8Connection, 10);
                }
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data piutang dari database Acosys V8: " . $e->getMessage();
            }
        }

        return view('modules.v8_laporan_piutang.index', compact(
            'account',
            'v8Connection',
            'setting',
            'searchQuery',
            'selectedCustomerId',
            'summary',
            'topDebtors',
            'searchResults',
            'customerDetail',
            'connectionError'
        ));
    }
}
