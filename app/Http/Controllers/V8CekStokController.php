<?php

namespace App\Http\Controllers;

use App\Models\AcosysConnection;
use App\Models\BotTeleSetting;
use App\Services\AcosysConnector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class V8CekStokController extends Controller
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
     * Web UI: Dashboard Modul Cek Stok Produk (Acosys V8)
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
        $searchResults = [];
        $criticalProducts = [];
        $connectionError = null;

        if ($v8Connection) {
            try {
                if ($searchQuery !== '') {
                    $matches = $this->connector->searchProducts($v8Connection, $searchQuery, 12);
                    foreach ($matches as $m) {
                        $stockInfo = $this->connector->getProductStockDetail($v8Connection, $m['id']);
                        if ($stockInfo) {
                            $searchResults[] = $stockInfo;
                        }
                    }
                } else {
                    $criticalProducts = $this->connector->getCriticalStockProducts($v8Connection, 30);
                }
            } catch (Throwable $e) {
                $connectionError = "Gagal memuat data dari database Acosys V8: " . $e->getMessage();
            }
        }

        return view('modules.v8_cek_stok.index', compact(
            'account',
            'v8Connection',
            'setting',
            'searchQuery',
            'searchResults',
            'criticalProducts',
            'connectionError'
        ));
    }
}
