<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeRedeemV3 extends Model
{
    protected $table = 'prize_redeems_v3';

    protected $fillable = [
        'user_id',
        'prize_id',
        'point_used',
        'shipped_at',
        'shipping_proof_path',
        'proof_path',
    ];
}
