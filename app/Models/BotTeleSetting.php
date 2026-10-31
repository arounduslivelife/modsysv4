<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

class BotTeleSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'connection_id',
        'bot_token',
        'bot_username',
        'chat_id',
        'is_active',
        'notify_daily_sales',
        'daily_sales_time',
        'notify_overdue_debt',
        'notify_low_stock',
        'stock_threshold',
        'last_test_at',
        'last_test_ok',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notify_daily_sales' => 'boolean',
            'notify_overdue_debt' => 'boolean',
            'notify_low_stock' => 'boolean',
            'stock_threshold' => 'integer',
            'last_test_ok' => 'boolean',
            'last_test_at' => 'datetime',
        ];
    }

    public function setBotTokenAttribute($value): void
    {
        $this->attributes['bot_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDecryptedBotTokenAttribute(): string
    {
        try {
            return $this->attributes['bot_token'] ? Crypt::decryptString($this->attributes['bot_token']) : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcosysConnection::class, 'connection_id');
    }

    /**
     * Send message via Telegram Bot API.
     */
    public function sendMessage(string $text, ?string $targetChatId = null, string $parseMode = 'Markdown'): array
    {
        $token = $this->decrypted_bot_token;
        $chatId = $targetChatId ?: $this->chat_id;

        if (empty($token)) {
            return ['status' => false, 'message' => 'Bot Token Telegram belum dikonfigurasi.'];
        }

        if (empty($chatId)) {
            return ['status' => false, 'message' => 'Chat ID Telegram tujuan belum diisi.'];
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => $parseMode,
                    'disable_web_page_preview' => true,
                ]);

            $json = $response->json();

            if ($response->successful() && !empty($json['ok'])) {
                return [
                    'status' => true,
                    'message' => 'Pesan Telegram berhasil dikirim!',
                    'data' => $json['result'] ?? [],
                ];
            }

            return [
                'status' => false,
                'message' => 'Gagal mengirim ke Telegram: ' . ($json['description'] ?? 'Kesalahan tidak diketahui'),
            ];
        } catch (Throwable $e) {
            return [
                'status' => false,
                'message' => 'Koneksi ke Telegram API gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test bot token by calling getMe.
     */
    public function testGetMe(?string $tokenToTest = null): array
    {
        $token = $tokenToTest ?: $this->decrypted_bot_token;

        if (empty($token)) {
            return ['status' => false, 'message' => 'Bot Token Telegram kosong.'];
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            $json = $response->json();

            if ($response->successful() && !empty($json['ok'])) {
                $bot = $json['result'];
                return [
                    'status' => true,
                    'message' => "Bot Telegram valid! Terhubung ke @{$bot['username']} ({$bot['first_name']}).",
                    'bot_username' => $bot['username'] ?? '',
                    'bot_id' => $bot['id'] ?? '',
                ];
            }

            return [
                'status' => false,
                'message' => 'Token Telegram tidak valid: ' . ($json['description'] ?? 'Unauthorized'),
            ];
        } catch (Throwable $e) {
            return [
                'status' => false,
                'message' => 'Gagal menghubungi server Telegram: ' . $e->getMessage(),
            ];
        }
    }
}
