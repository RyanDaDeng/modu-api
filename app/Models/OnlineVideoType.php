<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnlineVideoType extends Model
{
    use HasFactory;
    protected $connection = 'video';
    protected $guarded = [];
}
