<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WaGatewayClient
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('WA_GATEWAY_URL', 'http://localhost:3000'), '/');
        $this->apiKey = env('WA_GATEWAY_KEY', 'modsysv4_secret_key_2026');
    }

    protected function client()
    {
        return Http::withoutVerifying()
            ->withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(15);
    }

    public function createSession(string $sessionKey): array
    {
        try {
            $res = $this->client()->post("{$this->baseUrl}/sessions", [
                'session_key' => $sessionKey,
            ]);
            return $res->json() ?: ['status' => false, 'message' => 'Respon gateway kosong'];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Gateway tidak dapat dijangkau: ' . $e->getMessage()];
        }
    }

    public function getQr(string $sessionKey): array
    {
        try {
            $res = $this->client()->get("{$this->baseUrl}/sessions/{$sessionKey}/qr");
            return $res->json() ?: ['status' => false, 'message' => 'Respon gateway kosong'];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Gateway tidak dapat dijangkau: ' . $e->getMessage()];
        }
    }

    public function getStatus(string $sessionKey): array
    {
        try {
            $res = $this->client()->get("{$this->baseUrl}/sessions/{$sessionKey}/status");
            return $res->json() ?: ['status' => false, 'message' => 'Respon gateway kosong'];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Gateway offline: ' . $e->getMessage()];
        }
    }

    public function deleteSession(string $sessionKey): array
    {
        try {
            $res = $this->client()->delete("{$this->baseUrl}/sessions/{$sessionKey}");
            return $res->json() ?: ['status' => false, 'message' => 'Respon gateway kosong'];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Gateway offline: ' . $e->getMessage()];
        }
    }

    public function sendMessage(string $sessionKey, string $phone, string $text): array
    {
        try {
            $res = $this->client()->post("{$this->baseUrl}/messages/send", [
                'session_key' => $sessionKey,
                'phone' => $phone,
                'message' => $text,
            ]);
            return $res->json() ?: ['status' => false, 'message' => 'Respon gateway kosong'];
        } catch (Throwable $e) {
            return ['status' => false, 'message' => 'Gagal mengirim pesan: ' . $e->getMessage()];
        }
    }
}
