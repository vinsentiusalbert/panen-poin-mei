<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contact_infos_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone', 30);
            $table->string('address', 255);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        if (Schema::hasTable('user_contact_infos') && DB::table('user_contact_infos')->exists()) {
            DB::table('user_contact_infos_v2')->insertUsing(
                ['id', 'user_id', 'phone', 'address', 'remark', 'created_at', 'updated_at'],
                DB::table('user_contact_infos')->select('id', 'user_id', 'phone', 'address', 'remark', 'created_at', 'updated_at')
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contact_infos_v2');
    }
};
