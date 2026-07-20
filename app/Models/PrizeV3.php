<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeV3 extends Model
{
    protected $table = 'prizes_v3';

    protected $fillable = [
        'img',
        'name',
        'point',
        'stock',
    ];
}
