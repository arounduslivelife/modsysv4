<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class AcosysConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'label',
        'host',
        'port',
        'database',
        'username',
        'password',
        'last_test_at',
        'last_test_ok',
        'test_message',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'last_test_ok' => 'boolean',
            'last_test_at' => 'datetime',
        ];
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = Crypt::encryptString($value ?? '');
    }

    public function getDecryptedPasswordAttribute(): string
    {
        try {
            return Crypt::decryptString($this->attributes['password'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function reminderSetting(): HasOne
    {
        return $this->hasOne(ReminderSetting::class, 'connection_id');
    }

    public function reminderContacts(): HasMany
    {
        return $this->hasMany(ReminderContact::class, 'connection_id');
    }
}
