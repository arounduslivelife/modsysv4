<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class CronController extends Controller
{
    /**
     * Verifikasi kunci keamanan cron link
     */
    protected function authorizeKey(Request $request): bool
    {
        $validKey = env('CRON_SECRET', env('WA_GATEWAY_KEY', 'modsysv4_secret_key_2026'));
        $providedKey = $request->query('key') ?? $request->header('X-Cron-Key') ?? $request->input('key');

        return !empty($providedKey) && hash_equals((string)$validKey, (string)$providedKey);
    }

    /**
     * Endpoint untuk menjalankan pengingat piutang otomatis via URL link
     * URL: https://domain/cron/send-piutang?key=...[&force=1]
     */
    public function sendPiutang(Request $request)
    {
        if (!$this->authorizeKey($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Kunci cron tidak valid atau tidak disertakan (?key=...).',
            ], 403);
        }

        $isForce = $request->boolean('force');
        $output = new BufferedOutput();

        try {
            $params = [];
            if ($isForce) {
                $params['--force'] = true;
            }

            $exitCode = Artisan::call('reminder:send-piutang', $params, $output);
            $outputText = $output->fetch();

            return response()->json([
                'status' => $exitCode === 0 ? 'success' : 'warning',
                'message' => 'Tugas pengingat piutang telah dieksekusi.',
                'timestamp' => now()->toDateTimeString(),
                'force' => $isForce,
                'output' => trim($outputText) ?: 'Tidak ada proses pengiriman yang dijadwalkan saat ini.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses pengingat: ' . $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }
    }

    /**
     * Endpoint untuk menjalankan seluruh jadwal task scheduler Laravel via URL link
     * URL: https://domain/cron/schedule-run?key=...
     */
    public function scheduleRun(Request $request)
    {
        if (!$this->authorizeKey($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Kunci cron tidak valid atau tidak disertakan (?key=...).',
            ], 403);
        }

        $output = new BufferedOutput();

        try {
            $exitCode = Artisan::call('schedule:run', [], $output);
            $outputText = $output->fetch();

            return response()->json([
                'status' => $exitCode === 0 ? 'success' : 'warning',
                'message' => 'Laravel scheduler telah dieksekusi via URL.',
                'timestamp' => now()->toDateTimeString(),
                'output' => trim($outputText) ?: 'No scheduled commands are ready to run.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kesalahan pada scheduler: ' . $e->getMessage(),
                'timestamp' => now()->toDateTimeString(),
            ], 500);
        }
    }
}
