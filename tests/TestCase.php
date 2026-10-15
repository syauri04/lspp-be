<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: RefreshDatabase menjalankan migrate:fresh (DROP semua tabel).
     * Tolak kalau DB yang dipakai bukan DB test, misalnya karena config ter-cache
     * sehingga env dari phpunit.xml tidak terbaca.
     */
    protected function beforeRefreshingDatabase()
    {
        $db = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($db, '_test') && $db !== ':memory:') {
            throw new RuntimeException("Test dibatalkan: database '{$db}' bukan database test. Jalankan 'php artisan config:clear'.");
        }
    }
}
