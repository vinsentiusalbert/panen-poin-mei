<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeRedeemV5 extends Model
{
    protected $table = 'prize_redeems_v5';
    protected $guarded = [];

    public function prize()
    {
        return $this->belongsTo(PrizeV5::class, 'prize_id');
    }
}
