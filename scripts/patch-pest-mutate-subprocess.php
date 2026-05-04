<?php

declare(strict_types=1);

/**
 * Patches pest-plugin-mutate MutationTest for Laravel-sized subprocess work:
 *
 * 1. Strip mutant argv flags that are wrong or expensive in subprocesses: all `--coverage*`,
 *    `--mutate`, `--parallel`, `-p`, `--processes=*` (mutants are plain filtered test runs).
 * 2. Floor per-mutant timeout: Pest uses `initialSuiteDuration + max(5, 20%)`. Scoped runs
 *    make `initial` a few seconds → ~9s cap. Override: PEST_MUTATION_TIMEOUT_FLOOR_SECONDS
 *    (integer seconds, minimum 60 when set).
 *
 * Idempotent; must run before relying on vendor (place early in composer post-autoload-dump
 * so a failing `artisan package:discover` does not skip this patch).
 */
$path = dirname(__DIR__).'/vendor/pestphp/pest-plugin-mutate/src/MutationTest.php';

if (! is_readable($path)) {
    exit(0);
}

$src = file_get_contents($path);
if ($src === false) {
    fwrite(STDERR, "patch-pest-mutate-subprocess: could not read {$path}\n");
    exit(1);
}

$changed = false;

$narrowArgvLoop = <<<'PHP'
        foreach ($originalArguments as $argument) {
            if ($argument === '--coverage-php' || str_starts_with((string) $argument, '--coverage-php=')) {
                continue;
            }

            $mutationArgv[] = $argument;
        }
PHP;

$expandedArgvLoop = <<<'PHP'
        // PATCH_PEST_MUTATE_STRIP_ARGV_EXPANDED: subprocess must not re-run mutate/coverage/parallel.
        foreach ($originalArguments as $argument) {
            $arg = (string) $argument;
            if ($arg === '--mutate' || $arg === '--parallel' || $arg === '-p') {
                continue;
            }
            if (str_starts_with($arg, '--coverage')) {
                continue;
            }
            if (str_starts_with($arg, '--processes=')) {
                continue;
            }

            $mutationArgv[] = $argument;
        }
PHP;

if (! str_contains($src, 'PATCH_PEST_MUTATE_STRIP_ARGV_EXPANDED')) {
    $argvNeedleStock = <<<'PHP'
        // TODO: filter arguments to remove unnecessary stuff (Teamcity, Coverage, etc.)
        $process = new Process(
            command: [
                ...$originalArguments,
                '--bail',
PHP;

    $argvReplacementStock = <<<'PHP'
        // TODO: filter arguments to remove unnecessary stuff (Teamcity, Coverage, etc.)
        // PATCH_PEST_MUTATE_STRIP_COVERAGE (see STRIP_ARGV_EXPANDED in this script).
        $mutationArgv = [];
        // PATCH_PEST_MUTATE_STRIP_ARGV_EXPANDED: subprocess must not re-run mutate/coverage/parallel.
        foreach ($originalArguments as $argument) {
            $arg = (string) $argument;
            if ($arg === '--mutate' || $arg === '--parallel' || $arg === '-p') {
                continue;
            }
            if (str_starts_with($arg, '--coverage')) {
                continue;
            }
            if (str_starts_with($arg, '--processes=')) {
                continue;
            }

            $mutationArgv[] = $argument;
        }

        $process = new Process(
            command: [
                ...$mutationArgv,
                '--bail',
PHP;

    if (str_contains($src, $argvNeedleStock)) {
        $src = str_replace($argvNeedleStock, $argvReplacementStock, $src);
        $changed = true;
    } elseif (str_contains($src, 'PATCH_PEST_MUTATE_STRIP_COVERAGE') && str_contains($src, $narrowArgvLoop)) {
        $src = str_replace($narrowArgvLoop, $expandedArgvLoop, $src);
        $changed = true;
    }
}

// Normalize older patch (120/30) to current floor defaults.
if (str_contains($src, 'PATCH_PEST_MUTATE_TIMEOUT_FLOOR')) {
    $srcNorm = str_replace(
        '$floor = ($floorEnv !== false && $floorEnv !== \'\') ? max(30, (int) $floorEnv) : 120;',
        '$floor = ($floorEnv !== false && $floorEnv !== \'\') ? max(60, (int) $floorEnv) : 240;',
        $src
    );
    if ($srcNorm !== $src) {
        $src = $srcNorm;
        $changed = true;
    }
}

if (str_contains($src, 'PEST_MUTATION_TIMEOUT_FLOOR_SECONDS (default 120).')) {
    $srcC = str_replace(
        'PEST_MUTATION_TIMEOUT_FLOOR_SECONDS (default 120).',
        'PEST_MUTATION_TIMEOUT_FLOOR_SECONDS (default 240, minimum 60 when set).',
        $src
    );
    if ($srcC !== $src) {
        $src = $srcC;
        $changed = true;
    }
}

if (! str_contains($src, 'PATCH_PEST_MUTATE_TIMEOUT_FLOOR')) {
    $timeoutNeedle = <<<'PHP'
        return (int) ($initialTestSuiteDuration + max(5, $initialTestSuiteDuration * 0.2));
PHP;

    $timeoutReplacement = <<<'PHP'
        // PATCH_PEST_MUTATE_TIMEOUT_FLOOR: scoped runs yield tiny `initial`; stock cap is ~initial+5s (often <10s) — too small for Laravel bootstrap per subprocess. Optional override: PEST_MUTATION_TIMEOUT_FLOOR_SECONDS (default 240, minimum 60 when set).
        $floorEnv = getenv('PEST_MUTATION_TIMEOUT_FLOOR_SECONDS');
        $floor = ($floorEnv !== false && $floorEnv !== '') ? max(60, (int) $floorEnv) : 240;
        $computed = $initialTestSuiteDuration + max(5, $initialTestSuiteDuration * 0.2);

        return max($floor, (int) ceil($computed));
PHP;

    if (str_contains($src, $timeoutNeedle)) {
        $src = str_replace($timeoutNeedle, $timeoutReplacement, $src);
        $changed = true;
    }
}

if ($changed) {
    file_put_contents($path, $src);
}
