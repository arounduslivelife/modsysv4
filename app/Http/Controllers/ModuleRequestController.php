<?php

namespace App\Http\Controllers;

use App\Models\ModuleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $account = $user->getActiveAccount();

        $moduleId = $request->input('module_id');
        $note = $request->input('note');

        ModuleRequest::create([
            'account_id' => $account->id,
            'module_id' => $moduleId,
            'user_id' => $user->id,
            'status' => 'pending',
            'note' => $note,
        ]);

        return back()->with('success', 'Permintaan aktivasi modul telah dikirimkan ke administrator. Kami akan segera memprosesnya.');
    }
}
