<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContactInfoV4 extends Model
{
    protected $table = 'user_contact_infos_v4';

    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'remark',
    ];
}
