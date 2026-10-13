<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountModule;
use App\Models\Module;
use App\Models\ModuleRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Akses khusus administrator SaaS modsysv4.');
        }
    }

    public function dashboard()
    {
        $this->ensureAdmin();

        $totalUsers = User::count();
        $totalAccounts = Account::count();
        $activeModulesCount = AccountModule::where('is_active', true)->count();
        $pendingRequestsCount = ModuleRequest::where('status', 'pending')->count();

        $recentUsers = User::latest()->take(8)->get();
        $recentAccounts = Account::with('owner')->latest()->take(8)->get();
        $pendingRequests = ModuleRequest::with(['account', 'module', 'user'])->where('status', 'pending')->latest()->get();

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalAccounts',
            'activeModulesCount',
            'pendingRequestsCount',
            'recentUsers',
            'recentAccounts',
            'pendingRequests'
        ));
    }

    public function accounts()
    {
        $this->ensureAdmin();
        $accounts = Account::with(['owner', 'accountModules.module', 'connections'])->latest()->paginate(15);
        return view('admin.accounts', compact('accounts'));
    }

    public function accountDetail($id)
    {
        $this->ensureAdmin();
        $account = Account::with(['owner', 'members.user', 'connections', 'accountModules.module'])->findOrFail($id);
        $allModules = Module::where('is_published', true)->orderBy('sort_order')->get();

        $accountModulesMap = $account->accountModules->keyBy('module_id');

        return view('admin.account_detail', compact('account', 'allModules', 'accountModulesMap'));
    }

    /**
     * Admin updates module checklists & expiration dates for a tenant account
     */
    public function updateAccountModules(Request $request, $id)
    {
        $this->ensureAdmin();
        $account = Account::findOrFail($id);

        $modulesData = $request->input('modules', []);
        $allModules = Module::all();

        foreach ($allModules as $mod) {
            $data = $modulesData[$mod->id] ?? null;
            $isActive = !empty($data['is_active']);
            $startsAt = !empty($data['starts_at']) ? $data['starts_at'] : null;
            $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
            $note = $data['note'] ?? null;

            AccountModule::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'module_id' => $mod->id,
                ],
                [
                    'is_active' => $isActive,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'note' => $note,
                    'updated_by' => Auth::id(),
                ]
            );
        }

        return back()->with('success', "Lisensi modul untuk akun {$account->name} berhasil diperbarui.");
    }

    public function requests()
    {
        $this->ensureAdmin();
        $requests = ModuleRequest::with(['account', 'module', 'user'])->latest()->paginate(20);
        return view('admin.requests', compact('requests'));
    }

    public function approveRequest($id)
    {
        $this->ensureAdmin();
        $req = ModuleRequest::with(['account', 'module'])->findOrFail($id);

        // Activate module for 30 days by default
        AccountModule::updateOrCreate(
            [
                'account_id' => $req->account_id,
                'module_id' => $req->module_id,
            ],
            [
                'is_active' => true,
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addDays(30)->toDateString(),
                'note' => 'Disetujui dari permintaan user via panel',
                'updated_by' => Auth::id(),
            ]
        );

        $req->update(['status' => 'approved']);

        return back()->with('success', "Permintaan aktivasi modul {$req->module->name} telah disetujui (+30 hari aktif).");
    }

    public function rejectRequest($id)
    {
        $this->ensureAdmin();
        $req = ModuleRequest::findOrFail($id);
        $req->update(['status' => 'rejected']);

        return back()->with('success', "Permintaan aktivasi telah ditolak.");
    }

    public function users()
    {
        $this->ensureAdmin();
        $users = User::withCount('accounts')->latest()->paginate(20);
        return view('admin.users', compact('users'));
    }
}
