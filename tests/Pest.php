<?php

/*
| PHPUnit process isolation (`RunInSeparateProcess`) renders a child script that
| requires Composer autoload only when PHPUNIT_COMPOSER_INSTALL is defined.
| Composer's phpunit binary sets this; Pest's entrypoint does not — define it here
| so isolated tests work under `./vendor/bin/pest` and `pest --mutate`.
|
| Mutation testing:
| - `--path` is source to mutate (e.g. `app/Services/Logic`), not the test directory.
| - Optional trailing paths limit which tests run for the initial coverage pass, e.g.
|   `pest --mutate --path=app/Services/Logic tests/Unit/Services/Logic`.
| - `--everything --covered-only` with no `--path` often yields zero mutations (no files
|   to intersect with coverage); use `--everything` alone or add explicit `--path=app`.
| - Avoid `--parallel` with `--mutate`: the Parallel plugin still sees `--parallel` on the
|   real argv while coverage is merged from workers, so line→test mapping is missing and
|   Pest reports UNCOVERED for mutants even when tests exist. Run `pest --mutate` without
|   `--parallel` so the initial `--coverage-php` pass stays single-process and each mutant
|   gets correct test filters.
| - **PCOV `pcov.directory` must cover this checkout**: if php.ini points elsewhere (e.g. a
|   template path like `/Users/you/Work/...`), PHPUnit still records files/lines but **per-line
|   test ids stay empty** (`[]`), so Pest shows every mutant as UNCOVERED (0 tested). `ini_set`
|   cannot override this (SYSTEM). Fix Herd/PHP ini, or prefix PHP with `-d pcov.directory=.`
|   from `carpoolear_backend` (or pass an absolute project path). **`composer test:mutate`** runs
|   `scripts/pest-mutate.sh` (same `-d pcov.directory=.`); Composer must use **PHP ≥ 8.5** (`require.php`).
|   If your global `composer`/`php` is older, set **`PEST_PHP`** to PHP 8.5+ (e.g. Herd **`php85`**) or invoke `./vendor/bin/pest --mutate` with that binary directly.
| - Scoped runs finish the initial suite in a few seconds; Pest’s per-mutant timeout is
|   derived from that duration (~initial + 5s), which is too low for Laravel bootstrap.
|   `scripts/patch-pest-mutate-subprocess.php` (composer post-autoload-dump) floors the
|   timeout (default floor 240s; override with `PEST_MUTATION_TIMEOUT_FLOOR_SECONDS`).
| - If every mutant shows `t`, run `php scripts/patch-pest-mutate-subprocess.php` or
|   `composer dump-autoload` (patch runs before `package:discover` so it still applies when
|   discover fails).
| - `tests/bootstrap.php` skips MySQL lock + DROP/CREATE when `PEST_MUTATION_TESTING` is set
|   (mutant children); otherwise they block on the same `flock` as the parent and every mutant times out (`t`).
| - Two local Pest runs on the same default DB block each other on the MySQL bootstrap lock.
|   Use a separate schema: `DB_DATABASE=testing1 ./vendor/bin/pest …` (MySQL user needs
|   CREATE DATABASE; bootstrap creates the schema if missing).
*/
if (! defined('PHPUNIT_COMPOSER_INSTALL')) {
    if (isset($GLOBALS['_composer_autoload_path']) && is_string($GLOBALS['_composer_autoload_path']) && $GLOBALS['_composer_autoload_path'] !== '') {
        define('PHPUNIT_COMPOSER_INSTALL', $GLOBALS['_composer_autoload_path']);
    } elseif (file_exists($autoload = dirname(__DIR__).'/vendor/autoload.php')) {
        define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
