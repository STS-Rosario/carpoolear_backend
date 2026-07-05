<?php

namespace STS\Services\Admin;

use Illuminate\Support\Str;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tymon\JWTAuth\Facades\JWTAuth;

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

    /**
     * @return array{token: string, session: AdminImpersonationSession, target_user: User}
     */
    public function consume(string $plainToken): array
    {
        $session = AdminImpersonationSession::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($session === null || ! $session->isActive()) {
            abort(401, 'invalid_impersonation_token');
        }

        $session->consumed_at = now();
        $session->save();

        $targetUser = $session->targetUser;
        if ($targetUser === null) {
            abort(401, 'invalid_impersonation_token');
        }

        JWTAuth::factory()->setTTL(self::SESSION_TTL_MINUTES);

        $token = JWTAuth::claims([
            'imp' => true,
            'actor_id' => $session->admin_user_id,
            'session_id' => $session->id,
        ])->fromUser($targetUser);

        return [
            'token' => $token,
            'session' => $session,
            'target_user' => $targetUser,
        ];
    }

    public function stopSession(AdminImpersonationSession $session, ?User $actor = null): AdminImpersonationSession
    {
        if ($session->ended_at === null) {
            $session->ended_at = now();
            $session->save();
        }

        return $session->fresh();
    }

    public function findSessionOrFail(int $sessionId): AdminImpersonationSession
    {
        $session = AdminImpersonationSession::query()->find($sessionId);

        if ($session === null) {
            abort(404, 'impersonation_session_not_found');
        }

        return $session;
    }

    public function assertImpersonationSessionActive(AdminImpersonationSession $session): void
    {
        if ($session->ended_at !== null) {
            abort(401, 'impersonation_session_inactive');
        }

        if ($session->expires_at === null || $session->expires_at->isPast()) {
            abort(401, 'impersonation_session_inactive');
        }
    }

    public function assertCanImpersonate(User $admin, User $target): void
    {
        if (! config('carpoolear.impersonation_enabled', true)) {
            throw new HttpException(403, 'impersonation_disabled');
        }

        if (! $admin->is_admin) {
            throw new HttpException(403, 'impersonation_forbidden');
        }

        if ($target->is_admin) {
            throw new HttpException(422, 'cannot_impersonate_admin');
        }

        if ($target->banned) {
            throw new HttpException(422, 'cannot_impersonate_banned_user');
        }

        if (! $target->active) {
            throw new HttpException(422, 'cannot_impersonate_inactive_user');
        }
    }

    /**
     * @return array{token: string, session: AdminImpersonationSession}
     */
    public function reissueImpersonationToken(AdminImpersonationSession $session, User $targetUser): array
    {
        $this->assertImpersonationSessionActive($session);

        JWTAuth::factory()->setTTL(self::SESSION_TTL_MINUTES);

        $token = JWTAuth::claims([
            'imp' => true,
            'actor_id' => $session->admin_user_id,
            'session_id' => $session->id,
        ])->fromUser($targetUser);

        return [
            'token' => $token,
            'session' => $session,
        ];
    }
}
