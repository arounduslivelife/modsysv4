<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'module_id',
        'is_active',
        'starts_at',
        'expires_at',
        'note',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isExpired(): bool
    {
        if (!$this->is_active) {
            return true;
        }
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }
}
