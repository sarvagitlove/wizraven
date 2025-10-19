<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'industry',
        'business_description',
        'website',
        'phone',
        'business_email',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'zip_code',
        'country',
        'year_established',
        'employees_count',
        'services_products',
        'target_market',
        'profile_status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the member profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who approved the profile.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if profile is approved.
     */
    public function isApproved()
    {
        return $this->profile_status === 'approved';
    }

    /**
     * Check if profile is signup pending.
     */
    public function isSignupPending()
    {
        return $this->profile_status === 'signup_pending';
    }

    /**
     * Check if profile is approval pending.
     */
    public function isApprovalPending()
    {
        return $this->profile_status === 'approval_pending';
    }
}
