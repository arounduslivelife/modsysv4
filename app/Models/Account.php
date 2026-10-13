<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'owner_user_id',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AccountMember::class, 'account_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_members', 'account_id', 'user_id')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(AccountInvitation::class, 'account_id');
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'account_modules', 'account_id', 'module_id')
            ->withPivot('is_active', 'starts_at', 'expires_at', 'note', 'updated_by')
            ->withTimestamps();
    }

    public function accountModules(): HasMany
    {
        return $this->hasMany(AccountModule::class, 'account_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(AcosysConnection::class, 'account_id');
    }

    public function waDevices(): HasMany
    {
        return $this->hasMany(WaDevice::class, 'account_id');
    }

    public function moduleRequests(): HasMany
    {
        return $this->hasMany(ModuleRequest::class, 'account_id');
    }

    public function hasActiveModule(string $moduleCode): bool
    {
        return $this->accountModules()
            ->whereHas('module', function ($q) use ($moduleCode) {
                $q->where('code', $moduleCode);
            })
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->exists();
    }
}
