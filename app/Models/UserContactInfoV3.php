<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContactInfoV3 extends Model
{
    protected $table = 'user_contact_infos_v3';

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'remark',
    ];
}
