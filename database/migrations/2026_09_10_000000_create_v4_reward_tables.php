<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $this->cloneTableWithData('akun_panen_poin_v3', 'akun_panen_poin_v4');
        $this->cloneTableWithData('summary_panen_poin_v3', 'summary_panen_poin_v4', false);
        $this->cloneTableWithData('prizes_v3', 'prizes_v4');
        $this->cloneTableWithData('user_contact_infos_v3', 'user_contact_infos_v4');

        if (!Schema::hasTable('prize_redeems_v4')) {
            Schema::create('prize_redeems_v4', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('prize_id');
                $table->integer('point_used');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('prize_id');
                $table->index(['user_id', 'created_at'], 'prize_redeems_v4_user_created_at_index');
            });
        }

        $this->ensurePrizeRedeemsV4Columns();
    }

    public function down(): void
    {
        Schema::dropIfExists('prize_redeems_v4');
        Schema::dropIfExists('user_contact_infos_v4');
        Schema::dropIfExists('prizes_v4');
        Schema::dropIfExists('summary_panen_poin_v4');
        Schema::dropIfExists('akun_panen_poin_v4');
    }

    private function cloneTableWithData(string $source, string $target, bool $copyData = true): void
    {
        if (Schema::hasTable($target)) {
            return;
        }

        if (!Schema::hasTable($source)) {
            throw new RuntimeException("Required source table is missing: {$source}");
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("CREATE TABLE `{$target}` LIKE `{$source}`");
            if ($copyData) {
                DB::statement("INSERT INTO `{$target}` SELECT * FROM `{$source}`");
            }
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TABLE {$target} AS SELECT * FROM {$source} WHERE 1 = 0");
            if ($copyData) {
                DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            }
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("CREATE TABLE {$target} (LIKE {$source} INCLUDING ALL)");
            if ($copyData) {
                DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            }
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement("SELECT TOP 0 * INTO {$target} FROM {$source}");
            if ($copyData) {
                DB::statement("INSERT INTO {$target} SELECT * FROM {$source}");
            }
            return;
        }

        throw new RuntimeException("Unsupported database driver for v4 table cloning: {$driver}");
    }

    private function ensurePrizeRedeemsV4Columns(): void
    {
        if (!Schema::hasTable('prize_redeems_v4')) {
            return;
        }

        Schema::table('prize_redeems_v4', function (Blueprint $table) {
            if (!Schema::hasColumn('prize_redeems_v4', 'point_used')) {
                $table->integer('point_used')->default(0)->after('prize_id');
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'period_start')) {
                $table->date('period_start')->nullable();
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'period_end')) {
                $table->date('period_end')->nullable();
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'shipping_proof_path')) {
                $table->string('shipping_proof_path')->nullable()->after('shipped_at');
            }

            if (!Schema::hasColumn('prize_redeems_v4', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('shipping_proof_path');
            }
        });
    }
};
