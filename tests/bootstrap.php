<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap (runs before the test suite)
|--------------------------------------------------------------------------
|
| Loads Composer autoload and ensures the MySQL schema named in phpunit.xml
| exists. Without this, the first PDO connection to DB_DATABASE can fail with
| "Unknown database", and partial migrate:fresh runs can leave broken state.
|
*/

require dirname(__DIR__).'/vendor/autoload.php';

$dbDriver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: 'mysql';
$dbName = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';

if ($dbDriver !== 'mysql' || $dbName === '' || $dbName === ':memory:') {
    return;
}

if (! preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
    fwrite(STDERR, "tests/bootstrap: refusing unsafe DB_DATABASE identifier.\n");

    exit(1);
}

$dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbUser = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$dbCharset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';
$dbCollation = $_ENV['DB_COLLATION'] ?? getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci';

if (! preg_match('/^[a-zA-Z0-9_]+$/', $dbCharset) || ! preg_match('/^[a-zA-Z0-9_]+$/', $dbCollation)) {
    fwrite(STDERR, "tests/bootstrap: refusing unsafe DB_CHARSET / DB_COLLATION.\n");

    exit(1);
}

$dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $dbHost, $dbPort, $dbCharset);

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
$sslCa = $_ENV['MYSQL_ATTR_SSL_CA'] ?? getenv('MYSQL_ATTR_SSL_CA') ?: '';
if ($sslCa !== '' && extension_loaded('pdo_mysql') && defined('\Pdo\Mysql::ATTR_SSL_CA')) {
    $pdoOptions[\Pdo\Mysql::ATTR_SSL_CA] = $sslCa;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
    $quotedDb = '`'.str_replace('`', '``', $dbName).'`';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDb} DEFAULT CHARACTER SET {$dbCharset} COLLATE {$dbCollation}");
} catch (PDOException $e) {
    fwrite(STDERR, 'tests/bootstrap: could not create testing database ('.$dbName.'): '.$e->getMessage().PHP_EOL);

    throw $e;
}
