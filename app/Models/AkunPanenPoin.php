<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AkunPanenPoin extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'akun_panen_poin_v3';

    protected $fillable = [
        'uuid',
        'user_id',
        'nama_akun',
        'email_client',
        'password',
        'source',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];


    /**
     * Relasi ke tabel users
     */
    protected $casts = [
        'uuid' => 'string',
        'password' => 'hashed',
    ];
    
}
