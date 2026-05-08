<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContactInfoV2 extends Model
{
    protected $table = 'user_contact_infos_v2';

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'remark',
    ];
}
