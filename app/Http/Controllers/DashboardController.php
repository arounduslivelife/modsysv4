<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $account = $user->getActiveAccount();

        if (!$account) {
            return redirect()->route('settings.connections')->with('info', 'Silakan buat akun usaha pertama Anda.');
        }

        $connections = $account->connections;
        $modules = Module::where('is_published', true)->orderBy('sort_order')->get();
        $activeModulesCount = $account->accountModules()->where('is_active', true)->count();
        $waDevices = $account->waDevices;

        return view('dashboard.index', compact('account', 'connections', 'modules', 'activeModulesCount', 'waDevices'));
    }

    public function switchAccount(Request $request)
    {
        $user = Auth::user();
        $account = $user->accounts()->findOrFail($request->input('account_id'));
        session(['active_account_id' => $account->id]);
        session()->forget('active_connection_id');

        return back()->with('success', "Beralih ke akun usaha {$account->name}");
    }

    public function switchConnection(Request $request)
    {
        $connectionId = $request->input('connection_id');
        session(['active_connection_id' => $connectionId]);

        return back()->with('success', 'Koneksi database aktif diperbarui.');
    }
}
