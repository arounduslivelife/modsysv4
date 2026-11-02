<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class V8LaporanHutangController extends Controller
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
     * Web UI: Dashboard Modul Laporan Hutang (Acosys V8)
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
        $selectedSupplierId = trim($request->input('supplier_id', ''));

        $summary = ['total_suppliers' => 0, 'total_payable' => 0, 'formatted_total' => 'Rp 0'];
        $topCreditors = [];
        $searchResults = [];
        $supplierDetail = null;
        $connectionError = null;

        if ($v8Connection) {
            try {
                $summary = $this->connector->getPayableSummary($v8Connection);

                if ($selectedSupplierId !== '') {
                    $supplierDetail = $this->connector->getSupplierPayableDetail($v8Connection, $selectedSupplierId);
                }

                if ($searchQuery !== '') {
                    $searchResults = $this->connector->searchSupplierPayables($v8Connection, $searchQuery, 20);
                } else {
                    $topCreditors = $this->connector->getTopCreditors($v8Connection, 10);
                }
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data hutang dari database Acosys V8: " . $e->getMessage();
            }
        }

        return view('modules.v8_laporan_hutang.index', compact(
            'account',
            'v8Connection',
            'setting',
            'searchQuery',
            'selectedSupplierId',
            'summary',
            'topCreditors',
            'searchResults',
            'supplierDetail',
            'connectionError'
        ));
    }
}
