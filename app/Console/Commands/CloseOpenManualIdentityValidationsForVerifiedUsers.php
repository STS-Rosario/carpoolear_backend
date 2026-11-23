<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Models\User;
use STS\Services\IdentityVerificationOutcome;
use STS\Services\UserIdentityVerificationSuccessService;

class CloseOpenManualIdentityValidationsForVerifiedUsers extends Command
{
    protected $signature = 'manual-identity-validations:close-for-verified-users {--dry-run : Count rows that would be closed without updating}';

    protected $description = 'Close open manual identity validations for users already marked identity_validated';

    public function handle(UserIdentityVerificationSuccessService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $usersTouched = 0;
        $rowsClosed = 0;

        User::query()
            ->where('identity_validated', true)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($service, $dryRun, &$usersTouched, &$rowsClosed) {
                foreach ($users as $user) {
                    $outcomeName = $user->identity_validation_type === 'mercado_pago'
                        ? IdentityVerificationOutcome::NAME_CLOSED_AFTER_MP_SUCCESS
                        : IdentityVerificationOutcome::NAME_CLOSED_AFTER_USER_VERIFIED;

                    if ($dryRun) {
                        $count = $service->closeOpenManualIdentityValidationsForUser($user, [
                            'outcome_name' => $outcomeName,
                            'dry_run' => true,
                        ]);
                    } else {
                        $count = $service->closeOpenManualIdentityValidationsForUser($user, [
                            'outcome_name' => $outcomeName,
                        ]);
                    }

                    if ($count > 0) {
                        $usersTouched++;
                        $rowsClosed += $count;
                    }
                }
            });

        $verb = $dryRun ? 'Would close' : 'Closed';
        $this->info($verb.' '.$rowsClosed.' open manual validation row(s) for '.$usersTouched.' user(s).');

        return self::SUCCESS;
    }
}