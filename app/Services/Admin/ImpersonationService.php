<?php

namespace STS\Services\Admin;

use Illuminate\Support\Str;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;

class ImpersonationService
{
    public const SESSION_TTL_MINUTES = 60;

    /**
     * @return array{session: AdminImpersonationSession, handoff_token: string}
     */
    public function start(User $admin, User $target): array
    {
        $this->assertCanImpersonate($admin, $target);

        $handoffToken = Str::random(64);

        $session = AdminImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'token_hash' => hash('sha256', $handoffToken),
            'expires_at' => now()->addMinutes(self::SESSION_TTL_MINUTES),
        ]);

        return [
            'session' => $session,
            'handoff_token' => $handoffToken,
        ];
    }

    public function assertCanImpersonate(User $admin, User $target): void
    {
        if (! config('carpoolear.impersonation_enabled', true)) {
            abort(403, 'impersonation_disabled');
        }

        if (! $admin->is_admin) {
            abort(403, 'impersonation_forbidden');
        }

        if ($target->is_admin) {
            abort(422, 'cannot_impersonate_admin');
        }

        if ($target->banned) {
            abort(422, 'cannot_impersonate_banned_user');
        }

        if (! $target->active) {
            abort(422, 'cannot_impersonate_inactive_user');
        }
    }
}
