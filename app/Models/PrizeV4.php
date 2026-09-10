<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeV4 extends Model
{
    protected $table = 'prizes_v4';

    protected $fillable = [
        'img',
        'name',
        'point',
        'stock',
    ];
}
