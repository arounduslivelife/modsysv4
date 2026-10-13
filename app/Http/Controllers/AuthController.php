<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Models\AccountMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $adminEmails = array_map('trim', explode(',', env('ADMIN_EMAILS', 'aroundus.livelife@gmail.com')));
            $isAdmin = in_array(strtolower($googleUser->getEmail()), array_map('strtolower', $adminEmails));

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'is_admin' => $isAdmin,
                    'last_login_at' => now(),
                ]
            );

            // Create personal account if user has no account yet
            if ($user->accounts()->count() === 0) {
                $account = Account::create([
                    'name' => 'Toko ' . $user->name,
                    'owner_user_id' => $user->id,
                    'status' => 'active',
                ]);

                AccountMember::create([
                    'account_id' => $account->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                ]);
            }

            Auth::login($user, true);

            return redirect()->route('dashboard');
        } catch (Throwable $e) {
            return redirect()->route('login')->with('error', 'Gagal login dengan Google: ' . $e->getMessage());
        }
    }

    /**
     * Local Development Fast Login (enabled only when APP_ENV=local)
     */
    public function devLogin(Request $request)
    {
        if (config('app.env') !== 'local') {
            abort(404);
        }

        $email = $request->input('email', 'aroundus.livelife@gmail.com');
        $adminEmails = array_map('trim', explode(',', env('ADMIN_EMAILS', 'aroundus.livelife@gmail.com')));
        $isAdmin = in_array(strtolower($email), array_map('strtolower', $adminEmails));

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $request->input('name', 'Admin modsysv4'),
                'is_admin' => $isAdmin,
                'status' => 'active',
                'last_login_at' => now(),
            ]
        );

        if ($user->accounts()->count() === 0) {
            $account = Account::create([
                'name' => 'Toko ' . $user->name,
                'owner_user_id' => $user->id,
                'status' => 'active',
            ]);

            AccountMember::create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            // Add default testing connection
            $account->connections()->create([
                'label' => 'Server Uji Coba Acosys',
                'host' => 'agungds.web.id',
                'port' => 3306,
                'database' => 'db_acosys',
                'username' => 'db_acosys',
                'password' => 'admin123',
                'last_test_ok' => true,
                'last_test_at' => now(),
                'test_message' => 'Koneksi terverifikasi otomatis.',
            ]);
        }

        Auth::login($user, true);

        return redirect()->route('dashboard')->with('success', "Login pengujian berhasil sebagai {$user->email}");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }
}
