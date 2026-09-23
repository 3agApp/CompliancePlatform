<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tests run on SQLite, but production is MySQL, which rejects any identifier
 * longer than 64 characters. Compile every migration to MySQL DDL without a
 * server and check the names Laravel generates for indexes and keys.
 */
test('every migration fits within the mysql identifier length limit', function () {
    config(['database.connections.mysql_ddl' => [
        ...config('database.connections.mysql'),
        'host' => '127.0.0.1',
        'port' => 1,
    ]]);

    $testConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_ddl');
    Schema::clearResolvedInstance();

    try {
        $statements = DB::connection('mysql_ddl')->pretend(function () {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                (require $path)->up();
            }
        });
    } finally {
        DB::setDefaultConnection($testConnection);
        Schema::clearResolvedInstance();
    }

    $identifiers = collect($statements)
        ->flatMap(fn (array $statement): array => preg_match_all('/`([^`]+)`/', $statement['query'], $matches) ? $matches[1] : [])
        ->unique();

    expect($statements)->not->toBeEmpty()
        ->and($identifiers->filter(fn (string $identifier): bool => strlen($identifier) > 64)->values()->all())->toBe([]);
});
