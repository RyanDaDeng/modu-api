<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RedemptionCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'is_active',
        'reference',
        'redeemed_by',
        'redeemed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'integer',
        'redeemed_at' => 'datetime',
    ];

    /**
     * Get the user who redeemed this code
     */
    public function redeemedBy()
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /**
     * Generate a unique redemption code
     */
    public static function generateCode($prefix = 'VIP')
    {
        do {
            $code = $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());
        
        return $code;
    }

    /**
     * Check if code is redeemable
     */
    public function isRedeemable()
    {
        return $this->is_active && !$this->redeemed_by && !$this->redeemed_at;
    }

    /**
     * Redeem the code for a user
     */
    public function redeem(User $user)
    {
        if (!$this->isRedeemable()) {
            return false;
        }

        $this->update([
            'redeemed_by' => $user->id,
            'redeemed_at' => now(),
            'is_active' => false,
        ]);

        // Apply the redemption based on type
        if ($this->type === 'vip') {
            $user->addVipDays($this->value);
        }

        return true;
    }
}