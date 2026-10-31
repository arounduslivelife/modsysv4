<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotTeleSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'chat_id',
        'acosys_user_id',
        'acosys_user_name',
        'acosys_user_role',
        'state',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
