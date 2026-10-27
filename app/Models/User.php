<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'subscription_tier', 'subscription_expires_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function children(): HasMany { return $this->hasMany(Child::class); }
    public function deviceTokens(): HasMany { return $this->hasMany(DeviceToken::class); }

    public function isPro(): bool
    {
        return $this->subscription_tier === 'pro'
            && ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }
}
