<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar',
        'password',
        'is_admin',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        if ($this->is_admin) {
            return true;
        }
        $admins = explode(',', env('ADMIN_EMAILS', ''));
        $admins = array_map('trim', $admins);
        return in_array($this->email, $admins);
    }

    public function ownedAccounts(): HasMany
    {
        return $this->hasMany(Account::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMember::class, 'user_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_members', 'user_id', 'account_id')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function getActiveAccount(): ?Account
    {
        $accountId = session('active_account_id');
        if ($accountId) {
            $account = $this->accounts()->find($accountId);
            if ($account) {
                return $account;
            }
        }

        $account = $this->accounts()->first();
        if ($account) {
            session(['active_account_id' => $account->id]);
        }
        return $account;
    }
}
