<?php

namespace Tests\Feature;

use App\Models\AkunPanenPoin;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PanenPoinV4Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Carbon::setTestNow('2026-09-10 12:00:00');

        Schema::create('akun_panen_poin_v4', function (Blueprint $table) {
            $table->id();
            $table->string('email_client');
            $table->string('nama_akun');
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('prizes_v4', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('img');
            $table->integer('point');
            $table->integer('stock');
            $table->timestamps();
        });
        Schema::create('user_contact_infos_v4', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone');
            $table->string('address');
            $table->string('remark')->nullable();
            $table->timestamps();
        });
        Schema::create('summary_panen_poin_v4', function (Blueprint $table) {
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
        Schema::create('prize_redeems_v4', function (Blueprint $table) {
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
        DB::table('user_contact_infos_v4')->insert([
            'user_id' => $user->id, 'phone' => '628123456789', 'address' => 'Alamat test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('summary_panen_poin_v4')->insert([
            'email_client' => $user->email_client, 'poin' => 100,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2, 3] as $id) {
            DB::table('prizes_v4')->insert([
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

    public function test_redeem_uses_v4_and_updates_points_and_stock(): void
    {
        $this->postJson('/redeem', ['prize_id' => 1])->assertOk()->assertJson(['status' => true]);
        $this->assertDatabaseHas('prize_redeems_v4', [
            'prize_id' => 1, 'point_used' => 60,
            'period_start' => '2026-09-01', 'period_end' => '2026-10-09',
        ]);
        $this->assertDatabaseHas('summary_panen_poin_v4', ['poin_redeem' => 60]);
        $this->assertDatabaseHas('prizes_v4', ['id' => 1, 'stock' => 4]);
    }

    public function test_spent_points_cannot_be_used_again_in_october(): void
    {
        $this->postJson('/redeem', ['prize_id' => 1])->assertJson(['status' => true]);
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->postJson('/redeem', ['prize_id' => 2])->assertStatus(400)
            ->assertJson(['status' => false, 'message' => 'Poin tidak cukup untuk menukar hadiah ini']);
        $this->assertDatabaseCount('prize_redeems_v4', 1);
        $this->assertDatabaseHas('prizes_v4', ['id' => 2, 'stock' => 5]);
    }

    public function test_limit_and_duplicate_checks_cover_both_months(): void
    {
        DB::table('summary_panen_poin_v4')->update(['poin' => 1000]);
        $this->postJson('/redeem', ['prize_id' => 1])->assertJson(['status' => true]);
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->postJson('/redeem', ['prize_id' => 1])->assertStatus(400)
            ->assertJson(['message' => 'Anda sudah pernah redeem hadiah ini']);
        $this->postJson('/redeem', ['prize_id' => 2])->assertJson(['status' => true]);
        $this->postJson('/redeem', ['prize_id' => 3])->assertStatus(400)->assertJson(['status' => false]);
        $this->assertDatabaseCount('prize_redeems_v4', 2);
        $this->assertDatabaseHas('summary_panen_poin_v4', ['poin_redeem' => 120]);
    }

    public function test_redeem_period_includes_first_and_last_days_only(): void
    {
        DB::table('summary_panen_poin_v4')->update(['poin' => 1000]);
        Carbon::setTestNow('2026-08-31 23:59:59');
        $this->postJson('/redeem', ['prize_id' => 1])->assertJson(['status' => false]);
        Carbon::setTestNow('2026-09-01 00:00:00');
        $this->postJson('/redeem', ['prize_id' => 1])->assertJson(['status' => true]);
        Carbon::setTestNow('2026-10-09 23:59:59');
        $this->postJson('/redeem', ['prize_id' => 2])->assertJson(['status' => true]);
        Carbon::setTestNow('2026-10-10 00:00:00');
        $this->postJson('/redeem', ['prize_id' => 3])->assertJson(['status' => false]);
        $this->assertDatabaseCount('prize_redeems_v4', 2);
    }
}
