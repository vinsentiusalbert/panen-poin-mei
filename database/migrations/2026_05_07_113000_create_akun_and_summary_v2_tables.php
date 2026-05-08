<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cloneTableWithData('akun_panen_poin', 'akun_panen_poin_v2');
        $this->cloneTableWithData('summary_panen_poin', 'summary_panen_poin_v2');
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_panen_poin_v2');
        Schema::dropIfExists('akun_panen_poin_v2');
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

        throw new RuntimeException("Unsupported database driver for v2 table cloning: {$driver}");
    }
};
