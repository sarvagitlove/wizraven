<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'role_id',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the member profile associated with the user.
     */
    public function memberProfile()
    {
        return $this->hasOne(MemberProfile::class);
    }

    /**
     * Get the activation links for the user.
     */
    public function activationLinks()
    {
        return $this->hasMany(ActivationLink::class);
    }

    /**
     * Get the latest activation link for the user.
     */
    public function latestActivationLink()
    {
        return $this->hasOne(ActivationLink::class)->latest();
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin()
    {
        return $this->role && $this->role->role_name === 'admin';
    }

    /**
     * Check if user is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if user is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }
}
