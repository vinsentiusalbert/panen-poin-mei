<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_contact_infos_v4')) {
            Schema::create('user_contact_infos_v4', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('phone', 30);
                $table->string('address', 255);
                $table->text('remark')->nullable();
                $table->timestamps();
            });
        }

        // Preserve existing v4 contacts, including when retrying a partial migration.
        if (Schema::hasTable('user_contact_infos_v3')) {
            DB::table('user_contact_infos_v3')->orderByDesc('created_at')->orderByDesc('id')
                ->get()->each(function ($contact) {
                    DB::table('user_contact_infos_v4')->insertOrIgnore([
                        'user_id' => $contact->user_id,
                        'phone' => $contact->phone,
                        'address' => $contact->address,
                        'remark' => $contact->remark,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        // A repair rollback must not remove saved customer contact information.
    }
};
