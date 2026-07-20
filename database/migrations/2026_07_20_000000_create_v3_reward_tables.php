<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $this->cloneTableWithData('akun_panen_poin_v2', 'akun_panen_poin_v3');
        $this->cloneTableWithData('summary_panen_poin_v2', 'summary_panen_poin_v3');
        $this->cloneTableWithData('prizes_v2', 'prizes_v3');
        $this->cloneTableWithData('user_contact_infos_v2', 'user_contact_infos_v3');
        $this->cloneTableWithData('prize_redeems_v2', 'prize_redeems_v3');

        if (!Schema::hasTable('prize_redeems_v3')) {
            Schema::create('prize_redeems_v3', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('prize_id');
                $table->integer('point_used');
                $table->timestamps();

                $table->index('user_id');
                $table->index('prize_id');
                $table->index(['user_id', 'created_at'], 'prize_redeems_v3_user_created_at_index');
            });
        }

        $this->ensurePrizeRedeemsV3Columns();
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_redeems_v3');
        Schema::dropIfExists('user_contact_infos_v3');
        Schema::dropIfExists('prizes_v3');
        Schema::dropIfExists('summary_panen_poin_v3');
        Schema::dropIfExists('akun_panen_poin_v3');
    }

    private function cloneTableWithData(string $source, string $target): void
    {
        if (!Schema::hasTable($source) || Schema::hasTable($target)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("CREATE TABLE `{$target}` LIKE `{$source}`");
            DB::statement("INSERT INTO `{$target}` SELECT * FROM `{$source}`");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TABLE {$target} AS SELECT * FROM {$source} WHERE 1 = 0");
            DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE TABLE {$target} (LIKE {$source} INCLUDING ALL)");
            DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement("SELECT TOP 0 * INTO {$target} FROM {$source}");
            DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            return;
        }

        throw new RuntimeException("Unsupported database driver for v3 table cloning: {$driver}");
    }

    private function ensurePrizeRedeemsV3Columns(): void
    {
        if (!Schema::hasTable('prize_redeems_v3')) {
            return;
        }

        Schema::table('prize_redeems_v3', function (Blueprint $table) {
            if (!Schema::hasColumn('prize_redeems_v3', 'point_used')) {
                $table->integer('point_used')->default(0)->after('prize_id');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'shipping_proof_path')) {
                $table->string('shipping_proof_path')->nullable()->after('shipped_at');
            }

            if (!Schema::hasColumn('prize_redeems_v3', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('shipping_proof_path');
            }
        });
    }
};
