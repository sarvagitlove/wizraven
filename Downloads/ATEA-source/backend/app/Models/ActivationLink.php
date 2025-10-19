<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivationLink extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'email',
        'expires_at',
        'used_at',
        'sent_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'sent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the activation link.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new activation link.
     */
    public static function generate(User $user, $email = null)
    {
        return self::create([
            'user_id' => $user->id,
            'token' => Str::uuid(),
            'email' => $email ?? $user->email,
            'expires_at' => now()->addDays(45),
            'is_active' => true,
        ]);
    }

    /**
     * Check if the activation link is expired.
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    /**
     * Check if the activation link is used.
     */
    public function isUsed()
    {
        return !is_null($this->used_at);
    }

    /**
     * Check if the activation link is valid.
     */
    public function isValid()
    {
        return $this->is_active && !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Mark the activation link as used.
     */
    public function markAsUsed()
    {
        $this->update([
            'used_at' => now(),
            'is_active' => false,
        ]);
    }
}
