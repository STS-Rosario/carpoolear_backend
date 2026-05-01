<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap (runs before the test suite)
|--------------------------------------------------------------------------
|
| Loads Composer autoload and ensures the MySQL schema named in phpunit.xml exists.
| Without this, the first PDO connection to DB_DATABASE can fail with "Unknown database".
| PHPUnit’s phpunit.xml only sets some DB_* vars; Dotenv loads .env / .env.testing so host,
| username, and password match local MySQL before Laravel boots.
| DROP DATABASE IF EXISTS + CREATE DATABASE resets the named schema to an empty catalog so
| RefreshDatabase / migrate:fresh start from a known state (needs DROP privilege on that schema).
|
*/

require dirname(__DIR__).'/vendor/autoload.php';

$projectRoot = dirname(__DIR__);

// PHPUnit sets only some DB_* vars in phpunit.xml; load .env so DB_USERNAME / DB_PASSWORD
// (and host) match `artisan test` / local MySQL before Laravel boots.
if (is_file($projectRoot.'/.env')) {
    Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
}
if (is_file($projectRoot.'/.env.testing')) {
    Dotenv\Dotenv::createImmutable($projectRoot, '.env.testing')->safeLoad();
}

$env = static function (string $key, string $default = ''): string {
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    $v = getenv($key);

    return $v !== false ? (string) $v : $default;
};

$dbDriver = $env('DB_CONNECTION', 'mysql');
$dbName = $env('DB_DATABASE', '');

if ($dbDriver !== 'mysql' || $dbName === '' || $dbName === ':memory:') {
    return;
}

if (! preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
    fwrite(STDERR, "tests/bootstrap: refusing unsafe DB_DATABASE identifier.\n");

    exit(1);
}

$dbHost = $env('DB_HOST', '127.0.0.1');
$dbPort = $env('DB_PORT', '3306');
$dbUser = $env('DB_USERNAME', 'root');
$dbPass = $env('DB_PASSWORD', '');
$dbCharset = $env('DB_CHARSET', 'utf8mb4');
$dbCollation = $env('DB_COLLATION', 'utf8mb4_unicode_ci');

if (! preg_match('/^[a-zA-Z0-9_]+$/', $dbCharset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $dbCollation)) {
    fwrite(STDERR, "tests/bootstrap: refusing unsafe DB_CHARSET / DB_COLLATION.\n");

    exit(1);
}

// Serialize access to this MySQL schema so two concurrent `php artisan test` (or IDE + CLI)
// runs cannot interleave DROP/migrate/wipe on the same DB. Blocking flock; set
// TESTS_SKIP_MYSQL_FILE_LOCK=1 to disable (e.g. isolated CI jobs with unique DB names).
$skipLock = $env('TESTS_SKIP_MYSQL_FILE_LOCK', '');
if ($skipLock !== '1' && strtolower($skipLock) !== 'true') {
    $lockDir = $projectRoot.'/storage/framework';
    if (! is_dir($lockDir) && ! @mkdir($lockDir, 0755, true) && ! is_dir($lockDir)) {
        fwrite(STDERR, "tests/bootstrap: could not create storage/framework for MySQL lock.\n");

        exit(1);
    }
    $lockBasename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $dbName) ?: 'db';
    $lockPath = $lockDir.'/phpunit-mysql-'.$lockBasename.'.lock';
    $lockFp = fopen($lockPath, 'c');
    if ($lockFp === false) {
        fwrite(STDERR, "tests/bootstrap: could not open MySQL test lock file.\n");

        exit(1);
    }
    if (! flock($lockFp, LOCK_EX)) {
        fwrite(STDERR, "tests/bootstrap: could not acquire MySQL test lock.\n");

        exit(1);
    }
    register_shutdown_function(static function () use ($lockFp): void {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    });
}

$dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $dbHost, $dbPort, $dbCharset);

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
$sslCa = $env('MYSQL_ATTR_SSL_CA', '');
if ($sslCa !== '' && extension_loaded('pdo_mysql') && defined('\Pdo\Mysql::ATTR_SSL_CA')) {
    $pdoOptions[\Pdo\Mysql::ATTR_SSL_CA] = $sslCa;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
    $quotedDb = '`'.str_replace('`', '``', $dbName).'`';
    $pdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");
    $pdo->exec("CREATE DATABASE {$quotedDb} DEFAULT CHARACTER SET {$dbCharset} COLLATE {$dbCollation}");
} catch (PDOException $e) {
    fwrite(STDERR, 'tests/bootstrap: could not reset testing database ('.$dbName.'): '.$e->getMessage().PHP_EOL);

    throw $e;
}
