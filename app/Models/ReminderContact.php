<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'customer_id',
        'phone_override',
        'opt_out',
    ];

    protected function casts(): array
    {
        return [
            'opt_out' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcosysConnection::class, 'connection_id');
    }
}
