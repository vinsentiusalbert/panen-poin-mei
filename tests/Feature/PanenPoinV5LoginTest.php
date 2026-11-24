<?php

namespace Tests\Feature;

use App\Models\AkunPanenPoin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanenPoinV5LoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

        Schema::create('akun_panen_poin_v5', function (Blueprint $table) {
            $table->id();
            $table->string('email_client');
            $table->string('password');
            $table->timestamps();
        });

        AkunPanenPoin::create([
            'email_client' => 'ptvidyateknologidigital1@gmail.com',
            'password' => 'test-only-password',
        ]);
    }

    public function test_v5_account_can_log_in_without_a_v4_account(): void
    {
        $this->post('/login', [
            'email' => 'ptvidyateknologidigital1@gmail.com',
            'password' => 'test-only-password',
        ])->assertRedirect(route('home'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(AkunPanenPoin::first());
    }

    public function test_v5_account_cannot_log_in_with_wrong_password(): void
    {
        $this->post('/login', [
            'email' => 'ptvidyateknologidigital1@gmail.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_migration_preserves_password_and_login_without_overwriting_v5_accounts(): void
    {
        Schema::create('akun_panen_poin_v4', function (Blueprint $table) {
            $table->id();
            $table->string('email_client');
            $table->string('password');
        });
        $hash = Hash::make('admin-test-password');
        DB::table('akun_panen_poin_v4')->insert([
            'id' => 1, 'email_client' => 'arifasep@gmail.com', 'password' => $hash,
        ]);
        $migration = require database_path('migrations/2026_11_24_000001_copy_reward_admin_to_v5.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('akun_panen_poin_v5', 2);
        $this->assertDatabaseHas('akun_panen_poin_v5', ['id' => 1, 'email_client' => 'ptvidyateknologidigital1@gmail.com']);
        $this->assertDatabaseHas('akun_panen_poin_v5', ['email_client' => 'arifasep@gmail.com', 'password' => $hash]);
        $this->post('/login', ['email' => 'arifasep@gmail.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'arifasep@gmail.com', 'password' => 'admin-test-password'])
            ->assertRedirect(route('admin.redeems'));
        $this->assertAuthenticatedAs(AkunPanenPoin::where('email_client', 'arifasep@gmail.com')->first());
    }
}
