# Testing Runbook

Quick reference for local test execution, coverage, and parallel worker issues.

## Recommended commands

- Run all tests (single process):

```bash
php artisan test
```

- Run tests in parallel (recommended for speed):

```bash
TEST_TOKEN=1 php artisan test --parallel
```

- Run tests in parallel against a specific testing schema:

```bash
TEST_TOKEN=1 DB_DATABASE=testing8 php artisan test --parallel
```

- Run coverage (PCOV):

```bash
php -d pcov.enabled=1 -d pcov.directory="$(pwd)/app" artisan test --coverage
```

- Run coverage in parallel:

```bash
TEST_TOKEN=1 DB_DATABASE=testing8 php -d pcov.enabled=1 -d pcov.directory="$(pwd)/app" artisan test --parallel --coverage
```

## Why parallel runs can appear stuck

This repository has custom bootstrap logic in `tests/bootstrap.php` that can lock/reset the test database for single-process runs.  
Parallel workers must skip that lock/reset path, otherwise worker startup can block.

When `TEST_TOKEN` is present, bootstrap exits early from that lock/reset section so parallel workers can start correctly.

## Recovery steps when parallel run hangs

1. Stop the current test process.
2. Remove stale lock files:

```bash
rm -f storage/framework/phpunit-mysql-testing*.lock
```

3. Re-run with `TEST_TOKEN=1` and (optionally) an explicit `DB_DATABASE`.

## Notes

- Keep `LARAVEL_PARALLEL_TESTING` unset in `phpunit.xml` so `--parallel` works normally.
- If running multiple test sessions at once, use different `DB_DATABASE` values per session.
