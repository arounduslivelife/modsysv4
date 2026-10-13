<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleRequestController;
use App\Http\Controllers\PengingatPiutangController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (Landing & Legal for Google Consent)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('landing.index');
})->name('landing');

Route::get('/privacy', function () {
    return view('landing.privacy');
})->name('landing.privacy');

Route::get('/terms', function () {
    return view('landing.terms');
})->name('landing.terms');

/*
|--------------------------------------------------------------------------
| Webhook / Cron Link Trigger Routes (Akses Otomasi via URL)
|--------------------------------------------------------------------------
*/
Route::prefix('cron')->group(function () {
    Route::get('/send-piutang', [CronController::class, 'sendPiutang'])->name('cron.send-piutang');
    Route::get('/schedule-run', [CronController::class, 'scheduleRun'])->name('cron.schedule-run');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/dev-login', [AuthController::class, 'devLogin'])->name('auth.dev-login');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

/*
|--------------------------------------------------------------------------
| Authenticated SaaS Dashboard & Tenant Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/account/switch', [DashboardController::class, 'switchAccount'])->name('account.switch');
    Route::post('/connection/switch', [DashboardController::class, 'switchConnection'])->name('connection.switch');

    // Permintaan Beli / Aktivasi Modul (Klik Menu Abu-Abu)
    Route::post('/modules/request', [ModuleRequestController::class, 'store'])->name('module.request');

    // Pengaturan
    Route::prefix('settings')->name('settings.')->group(function () {
        // 1. Koneksi Database Acosys
        Route::get('/connections', [SettingsController::class, 'connections'])->name('connections');
        Route::post('/connections', [SettingsController::class, 'storeConnection'])->name('connections.store');
        Route::post('/connections/{id}/test', [SettingsController::class, 'testConnection'])->name('connections.test');
        Route::delete('/connections/{id}', [SettingsController::class, 'deleteConnection'])->name('connections.delete');

        // 2. Staf & Anggota
        Route::get('/staff', [SettingsController::class, 'staff'])->name('staff');
        Route::post('/staff/invite', [SettingsController::class, 'inviteStaff'])->name('staff.invite');
        Route::delete('/staff/{id}', [SettingsController::class, 'removeStaff'])->name('staff.delete');

        // 3. Perangkat WA Gateway
        Route::get('/wa-devices', [SettingsController::class, 'waDevice'])->name('wa-device');
        Route::post('/wa-devices', [SettingsController::class, 'storeWaDevice'])->name('wa-device.store');
        Route::get('/wa-devices/{id}/qr', [SettingsController::class, 'getWaDeviceQr'])->name('wa-device.qr');
        Route::delete('/wa-devices/{id}', [SettingsController::class, 'deleteWaDevice'])->name('wa-device.delete');
    });

    // Modul 1: Pengingat Piutang WA
    Route::prefix('modules/pengingat-piutang')->name('modules.pengingat-piutang.')->group(function () {
        Route::get('/', [PengingatPiutangController::class, 'index'])->name('index');
        Route::get('/customers/{customerId}/invoices', [PengingatPiutangController::class, 'getCustomerInvoices'])->name('invoices');
        Route::post('/contact', [PengingatPiutangController::class, 'saveContact'])->name('contact');
        Route::post('/send-single', [PengingatPiutangController::class, 'sendSingle'])->name('send-single');
        Route::get('/settings', [PengingatPiutangController::class, 'settings'])->name('settings');
        Route::post('/settings', [PengingatPiutangController::class, 'saveSettings'])->name('settings.save');
    });

    // Administrator SaaS
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/accounts', [AdminController::class, 'accounts'])->name('accounts');
        Route::get('/accounts/{id}', [AdminController::class, 'accountDetail'])->name('accounts.detail');
        Route::post('/accounts/{id}/modules', [AdminController::class, 'updateAccountModules'])->name('accounts.modules.update');
        Route::get('/requests', [AdminController::class, 'requests'])->name('requests');
        Route::post('/requests/{id}/approve', [AdminController::class, 'approveRequest'])->name('requests.approve');
        Route::post('/requests/{id}/reject', [AdminController::class, 'rejectRequest'])->name('requests.reject');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
    });
});
