<?php

namespace STS\Services\Maintenance;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use STS\Models\MaintenanceAuditLog;
use STS\Models\MaintenanceSchedule;
use STS\Models\MaintenanceState;

class MaintenanceStateService
{
    public function state(): MaintenanceState
    {
        return MaintenanceState::query()->findOrFail(1);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function writeAudit(?int $userId, string $action, array $meta = []): void
    {
        MaintenanceAuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'meta' => $meta ?: null,
            'created_at' => Carbon::now('UTC'),
        ]);
    }

    public function tick(?Carbon $now = null): void
    {
        $now = $now ?? Carbon::now('UTC');

        DB::transaction(function () use ($now): void {
            /** @var MaintenanceState $state */
            $state = MaintenanceState::query()->lockForUpdate()->findOrFail(1);

            if ($state->is_active && $state->ends_at && $state->ends_at->lte($now)) {
                $scheduleId = $state->active_schedule_id;
                $state->update([
                    'is_active' => false,
                    'mode' => null,
                    'message' => null,
                    'ends_at' => null,
                    'source' => null,
                    'active_schedule_id' => null,
                ]);
                if ($scheduleId !== null) {
                    MaintenanceSchedule::query()->whereKey($scheduleId)->update(['completed_at' => $now]);
                }
                $this->writeAudit(null, 'cron_deactivate', ['schedule_id' => $scheduleId]);

                $state->refresh();
            }

            if ($state->is_active) {
                return;
            }

            $schedule = MaintenanceSchedule::query()
                ->pending()
                ->where('starts_at', '<=', $now)
                ->where(function ($q) use ($now): void {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
                })
                ->orderBy('starts_at')
                ->lockForUpdate()
                ->first();

            if ($schedule === null) {
                return;
            }

            $state->update([
                'is_active' => true,
                'mode' => $schedule->mode,
                'message' => $schedule->message,
                'ends_at' => $schedule->ends_at,
                'source' => 'schedule',
                'active_schedule_id' => $schedule->id,
            ]);

            $this->writeAudit(null, 'cron_activate', ['schedule_id' => $schedule->id]);
        });
    }

    /**
     * Whether a pending schedule window overlaps any other pending schedule.
     */
    public function overlapsPendingSchedule(Carbon $startsAt, ?Carbon $endsAt, ?int $exceptScheduleId = null): bool
    {
        $schedules = MaintenanceSchedule::query()
            ->pending()
            ->when($exceptScheduleId !== null, fn ($q) => $q->where('id', '!=', $exceptScheduleId))
            ->get(['id', 'starts_at', 'ends_at', 'cancelled_at', 'completed_at']);

        foreach ($schedules as $existing) {
            if (MaintenanceInterval::overlap($startsAt, $endsAt, $existing->starts_at, $existing->ends_at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  'manual'|'schedule'  $source
     */
    public function applyManualActive(bool $active, ?string $mode, ?string $message, ?Carbon $endsAt, string $source, ?int $activeScheduleId, ?int $actorUserId): void
    {
        DB::transaction(function () use ($active, $mode, $message, $endsAt, $source, $activeScheduleId, $actorUserId): void {
            $state = MaintenanceState::query()->lockForUpdate()->findOrFail(1);

            if (! $active) {
                $linkedScheduleId = $state->active_schedule_id;
                $state->update([
                    'is_active' => false,
                    'mode' => null,
                    'message' => null,
                    'ends_at' => null,
                    'source' => null,
                    'active_schedule_id' => null,
                ]);
                if ($linkedScheduleId !== null) {
                    MaintenanceSchedule::query()->whereKey($linkedScheduleId)->update([
                        'cancelled_at' => Carbon::now('UTC'),
                    ]);
                }
                $this->writeAudit($actorUserId, 'manual_disable', [
                    'had_active_schedule_id' => $linkedScheduleId,
                ]);

                return;
            }

            $state->update([
                'is_active' => true,
                'mode' => $mode,
                'message' => $message ?? '',
                'ends_at' => $endsAt,
                'source' => $source,
                'active_schedule_id' => $activeScheduleId,
            ]);

            $this->writeAudit($actorUserId, 'manual_enable', [
                'mode' => $mode,
                'source' => $source,
                'active_schedule_id' => $activeScheduleId,
            ]);
        });
    }

    public function cancelSchedule(MaintenanceSchedule $schedule, ?int $actorUserId): void
    {
        DB::transaction(function () use ($schedule, $actorUserId): void {
            $schedule = MaintenanceSchedule::query()->whereKey($schedule->id)->lockForUpdate()->firstOrFail();

            if ($schedule->cancelled_at !== null) {
                return;
            }

            $schedule->update(['cancelled_at' => Carbon::now('UTC')]);

            $state = MaintenanceState::query()->lockForUpdate()->findOrFail(1);
            if ($state->active_schedule_id === (int) $schedule->id) {
                $state->update([
                    'is_active' => false,
                    'mode' => null,
                    'message' => null,
                    'ends_at' => null,
                    'source' => null,
                    'active_schedule_id' => null,
                ]);
            }

            $this->writeAudit($actorUserId, 'schedule_cancel', ['schedule_id' => $schedule->id]);
        });
    }

    /**
     * @return array{enabled: bool, mode: ?string, message: ?string, ends_at: ?string}
     */
    public function publicPayload(): array
    {
        $state = $this->state();

        if (! $state->is_active) {
            return [
                'enabled' => false,
                'mode' => null,
                'message' => null,
                'ends_at' => null,
            ];
        }

        return [
            'enabled' => true,
            'mode' => $state->mode,
            'message' => $state->message,
            'ends_at' => $state->ends_at?->toIso8601String(),
        ];
    }
}
