<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use STS\Services\UserMigrationUndo\UserMigrationUndoService;

class UndoUserMigration extends Command
{
    protected $signature = 'user:undo-migration {kept} {removed} {--dry-run : Report changes without writing} {--force : Skip confirmation prompt}';

    protected $description = 'Undo a user migration using a backup_db snapshot as reference';

    public function handle(UserMigrationUndoService $service): int
    {
        $keptId = (int) $this->argument('kept');
        $removedId = (int) $this->argument('removed');
        $dryRun = (bool) $this->option('dry-run');

        try {
            $service->validateInputs($keptId, $removedId);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Proceed with undo migration?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        try {
            $result = $service->undo($keptId, $removedId, $dryRun);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->displayResult($result);

        return self::SUCCESS;
    }

    private function displayResult(\STS\Services\UserMigrationUndo\UndoMigrationResult $result): void
    {
        if ($result->dryRun) {
            $this->info('Dry run complete. No changes were written.');
        } else {
            $this->info('Undo migration complete.');
        }
    }
}
