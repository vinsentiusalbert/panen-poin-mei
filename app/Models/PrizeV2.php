<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeV2 extends Model
{
    protected $table = 'prizes_v2';

    protected $fillable = [
        'img',
        'name',
        'point',
        'stock',
    ];
}
