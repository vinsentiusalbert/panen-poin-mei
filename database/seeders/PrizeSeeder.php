<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('prizes_v3')->truncate();

        DB::table('prizes_v3')->insert([
            [
                'img' => 'hadiah/Motor Vario 125 CBS.png',
                'name' => 'Motor Vario 125 CBS',
                'point' => 700,
                'stock' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/iPhone 17 Pro 256 GB.png',
                'name' => 'iPhone 17 Pro 256 GB',
                'point' => 600,
                'stock' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Logam Mulia 5 gram.png',
                'name' => 'Logam Mulia 5 gr',
                'point' => 380,
                'stock' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/ewallet.png',
                'name' => 'Saldo e-Wallet Rp10 Juta',
                'point' => 320,
                'stock' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Logam Mulia 2 Gram.png',
                'name' => 'Logam Mulia 2 Gram',
                'point' => 270,
                'stock' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/ewallet.png',
                'name' => 'Saldo e-Wallet Rp5 Juta',
                'point' => 260,
                'stock' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/ewallet.png',
                'name' => 'Saldo e-Wallet Rp3 Juta',
                'point' => 230,
                'stock' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Xiaomi Smart TV A 32 Pro.png',
                'name' => 'Xiaomi Smart TV A 32 Pro',
                'point' => 180,
                'stock' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Philip Series 2000 Airfryer.png',
                'name' => 'Philip Series 2000 Airfryer',
                'point' => 150,
                'stock' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/ewallet.png',
                'name' => 'Saldo e-Wallet Rp1 Juta',
                'point' => 130,
                'stock' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Samsung Galaxy Budscore.png',
                'name' => 'Samsung Galaxy Budscore White',
                'point' => 120,
                'stock' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Tumbler Stanley.png',
                'name' => 'Stanley Tumbler',
                'point' => 110,
                'stock' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/Cosmos Air Fryer.png',
                'name' => 'Cosmos Airfryer',
                'point' => 80,
                'stock' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/ewallet.png',
                'name' => 'Saldo e-Wallet Rp300 Ribu',
                'point' => 70,
                'stock' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'img' => 'hadiah/CM 208 A.png',
                'name' => 'Advance Coffee Maker CM 208 A',
                'point' => 40,
                'stock' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
