<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'rate',
        'last_check',
    ];

    protected $casts = [
        'last_check' => 'datetime',
        'rate' => 'integer',
    ];

    /**
     * Get the user that owns the affiliate.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}