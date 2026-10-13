<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'device_id',
        'enabled',
        'recipient_mode',
        'fixed_numbers',
        'send_time',
        'rules',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'rules' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AcosysConnection::class, 'connection_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }
}
