<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForumMember extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'uid';
    public $incrementing = true;

    protected $fillable = [
        'uid',
        'username',
        'email',
        'regdate'
    ];

    protected $casts = [
        'regdate' => 'datetime'
    ];
}