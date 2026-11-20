<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Hadiah V5
        if (!Schema::hasTable('prizes_v5')) {
            Schema::create('prizes_v5', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('points_required');
                $table->integer('stock')->default(0);
                $table->string('image')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 2. Tabel Penukaran Hadiah V5 (sudah digabung dengan kolom shipping)
        if (!Schema::hasTable('prize_redeems_v5')) {
            Schema::create('prize_redeems_v5', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('prize_id');
                $table->integer('points_spent');
                $table->string('status')->default('pending');
                $table->timestamp('shipped_at')->nullable();
                $table->string('shipping_proof_path')->nullable();
                $table->string('proof_path')->nullable();
                $table->timestamps();
            });
        }

        // 3. Tabel Kontak User V5
        if (!Schema::hasTable('user_contact_infos_v5')) {
            Schema::create('user_contact_infos_v5', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('phone', 30);
                $table->string('address', 255);
                $table->text('remark')->nullable();
                $table->timestamps();
            });
        }

        // Menyalin data kontak dari V4 ke V5 (mengikuti pola migrasi V3 -> V4)
        if (Schema::hasTable('user_contact_infos_v4')) {
            DB::table('user_contact_infos_v4')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->each(function ($contact) {
                    DB::table('user_contact_infos_v5')->insertOrIgnore([
                        'user_id'    => $contact->user_id,
                        'phone'      => $contact->phone,
                        'address'    => $contact->address,
                        'remark'     => $contact->remark,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at,
                    ]);
                });
        }

        // 4. Tabel Summary Poin V5 (menyalin struktur persis dari summary_panen_poin_v4)
        if (!Schema::hasTable('summary_panen_poin_v5') && Schema::hasTable('summary_panen_poin_v4')) {
            DB::statement('CREATE TABLE summary_panen_poin_v5 LIKE summary_panen_poin_v4');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_panen_poin_v5');
        Schema::dropIfExists('prize_redeems_v5');
        Schema::dropIfExists('prizes_v5');
        // user_contact_infos_v5 sengaja tidak di-drop saat rollback untuk menjaga keamanan data kontak
    }
};