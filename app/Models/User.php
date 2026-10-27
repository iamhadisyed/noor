<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'subscription_tier', 'subscription_expires_at', 'role', 'status', 'status_reason', 'last_login_at', 'terms_accepted_at', 'terms_version', 'notification_prefs'];

    protected $attributes = ['role' => 'parent', 'status' => 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'notification_prefs' => 'array',
            'password' => 'hashed',
        ];
    }

    public function children(): HasMany { return $this->hasMany(Child::class); }

    /** Ids of the parents whose children I can work with: me, plus every household I have joined. */
    public function householdOwnerIds(): array
    {
        return array_values(array_unique(array_merge(
            [$this->id],
            HouseholdMember::where('user_id', $this->id)->where('status', 'active')->pluck('owner_id')->all()
        )));
    }

    /** Own children and the children of households I have joined. Use this for anything a co-parent may do. */
    public function accessibleChildren(): Builder
    {
        return Child::whereIn('user_id', $this->householdOwnerIds());
    }

    /** Pro belongs to the household: a co-parent shares the plan of the parent who invited them. */
    public function hasProAccess(): bool
    {
        return $this->isPro() || User::whereIn('id', $this->householdOwnerIds())->get()->contains(fn ($u) => $u->isPro());
    }

    public function deviceTokens(): HasMany { return $this->hasMany(DeviceToken::class); }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPro(): bool
    {
        return $this->subscription_tier === 'pro'
            && ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }
}
