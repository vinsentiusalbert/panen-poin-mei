<?php

namespace Tests\Feature;

use App\Models\AkunPanenPoin;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PanenPoinV5RedeemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Carbon::setTestNow('2026-11-24 12:00:00');

        Schema::create('akun_panen_poin_v5', function (Blueprint $table) {
            $table->id();
            $table->string('email_client');
            $table->string('nama_akun');
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('prizes_v5', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('img');
            $table->integer('point');
            $table->integer('stock');
            $table->timestamps();
        });
        Schema::create('user_contact_infos_v5', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone');
            $table->string('address');
            $table->string('remark')->nullable();
            $table->timestamps();
        });
        Schema::create('summary_panen_poin_v5', function (Blueprint $table) {
            $table->id();
            $table->string('email_client');
            $table->integer('poin');
            $table->integer('poin_package')->default(0);
            $table->integer('poin_redeem')->default(0);
            $table->string('remark')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();
        });
        Schema::create('prize_redeems_v5', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('prize_id');
            $table->integer('point_used');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->string('shipping_proof_path')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();
        });

        $user = AkunPanenPoin::create([
            'email_client' => 'test@example.com', 'nama_akun' => 'Test', 'password' => 'password',
        ]);
        $this->actingAs($user);
        DB::table('user_contact_infos_v5')->insert([
            'user_id' => $user->id, 'phone' => '628123456789', 'address' => 'Alamat test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('summary_panen_poin_v5')->insert([
            'email_client' => $user->email_client, 'poin' => 100,
            'period_start' => '2026-11-13', 'period_end' => '2026-12-06',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2, 3] as $id) {
            DB::table('prizes_v5')->insert([
                'id' => $id, 'name' => "Hadiah {$id}", 'img' => 'test.png',
                'point' => 60, 'stock' => 5, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_exception_can_repeat_prize_and_exceed_two_redemptions(): void
    {
        $user = auth()->user();
        $user->update(['email_client' => 'ptvidyateknologidigital1@gmail.com']);
        DB::table('summary_panen_poin_v5')->update(['email_client' => $user->email_client, 'poin' => 180]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/redeem', ['prize_id' => 1])->assertOk()->assertJson(['status' => true]);
        }
        $this->assertDatabaseCount('prize_redeems_v5', 3);
        $this->assertDatabaseHas('prizes_v5', ['id' => 1, 'stock' => 2]);
        $this->assertDatabaseHas('summary_panen_poin_v5', ['poin_redeem' => 180]);
        $this->postJson('/redeem', ['prize_id' => 1])->assertStatus(400)
            ->assertJson(['message' => 'Poin tidak cukup untuk menukar hadiah ini']);
        DB::table('summary_panen_poin_v5')->update(['poin' => 1000]);
        DB::table('prizes_v5')->where('id', 1)->update(['stock' => 0]);
        $this->postJson('/redeem', ['prize_id' => 1])->assertStatus(400)
            ->assertJson(['message' => 'Stok hadiah habis']);
        $this->assertDatabaseCount('prize_redeems_v5', 3);
    }

    public function test_other_accounts_still_have_both_limits(): void
    {
        DB::table('summary_panen_poin_v5')->update(['poin' => 1000]);
        $this->postJson('/redeem', ['prize_id' => 1])->assertOk();
        $this->postJson('/redeem', ['prize_id' => 1])->assertStatus(400)
            ->assertJson(['message' => 'Anda sudah pernah redeem hadiah ini']);
        $this->postJson('/redeem', ['prize_id' => 2])->assertOk();
        $this->postJson('/redeem', ['prize_id' => 3])->assertStatus(400)
            ->assertJson(['message' => 'Anda sudah mencapai batas maksimal 2 redeem pada periode ini']);
        $this->assertDatabaseCount('prize_redeems_v5', 2);
    }
}
