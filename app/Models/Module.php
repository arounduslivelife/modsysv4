<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'acosys_version',
        'description',
        'icon',
        'route',
        'price_info',
        'sort_order',
        'is_published',
    ];

    public function isV8(): bool
    {
        return strtolower($this->acosys_version ?? 'v4') === 'v8';
    }

    public function isV4(): bool
    {
        return strtolower($this->acosys_version ?? 'v4') === 'v4';
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function accountModules(): HasMany
    {
        return $this->hasMany(AccountModule::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_modules')
            ->withPivot('is_active', 'starts_at', 'expires_at', 'note', 'updated_by')
            ->withTimestamps();
    }
}
