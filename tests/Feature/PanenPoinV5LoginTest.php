<?php

namespace Tests\Feature;

use App\Models\AkunPanenPoin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
}
