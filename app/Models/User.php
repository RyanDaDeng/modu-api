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
        'img_server',
        'vip_expired_at',
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
            'vip_expired_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's collections.
     */
    public function collections()
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * Get the user's payment orders.
     */
    public function paymentOrders()
    {
        return $this->hasMany(PaymentOrder::class);
    }

    /**
     * Get the user's bookmarks.
     */
    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Check if user has active VIP status
     */
    public function hasActiveVip()
    {
        // If vip_expired_at is null, user is not VIP
        if (!$this->vip_expired_at) {
            return false;
        }

        // Check if VIP hasn't expired
        return now()->lt($this->vip_expired_at);
    }
}
