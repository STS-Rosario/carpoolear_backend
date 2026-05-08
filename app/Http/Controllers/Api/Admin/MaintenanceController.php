<?php

namespace STS\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use STS\Http\Controllers\Controller;
use STS\Models\MaintenanceAuditLog;
use STS\Models\MaintenanceSchedule;
use STS\Services\Maintenance\MaintenanceStateService;

class MaintenanceController extends Controller
{
    public function __construct(
        protected MaintenanceStateService $maintenanceStateService
    ) {}

    public function schedulesIndex(): JsonResponse
    {
        $schedules = MaintenanceSchedule::query()
            ->pending()
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => $schedules]);
    }

    public function schedulesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date'],
            'message' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:strict,flexible'],
        ]);

        $startsAt = $this->parseUtcInstant($validated['starts_at']);
        $endsAt = isset($validated['ends_at']) && $validated['ends_at'] !== null
            ? $this->parseUtcInstant($validated['ends_at'])
            : null;

        $this->assertEndsAfterStart($startsAt, $endsAt);

        if ($this->maintenanceStateService->overlapsPendingSchedule($startsAt, $endsAt, null)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Overlaps an existing maintenance schedule.'],
            ]);
        }

        $schedule = MaintenanceSchedule::query()->create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'message' => $validated['message'],
            'mode' => $validated['mode'],
        ]);

        $this->maintenanceStateService->writeAudit((int) auth()->id(), 'schedule_create', [
            'schedule_id' => $schedule->id,
        ]);

        return response()->json(['data' => $schedule->fresh()], 201);
    }

    public function schedulesUpdate(Request $request, MaintenanceSchedule $schedule): JsonResponse
    {
        if (! $schedule->isPending()) {
            throw ValidationException::withMessages([
                'schedule' => ['This schedule can no longer be edited.'],
            ]);
        }

        $validated = $request->validate([
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'message' => ['sometimes', 'required', 'string'],
            'mode' => ['sometimes', 'required', 'string', 'in:strict,flexible'],
        ]);

        $startsAt = isset($validated['starts_at'])
            ? $this->parseUtcInstant($validated['starts_at'])
            : $schedule->starts_at;

        $endsAt = array_key_exists('ends_at', $validated)
            ? ($validated['ends_at'] !== null ? $this->parseUtcInstant($validated['ends_at']) : null)
            : $schedule->ends_at;

        $this->assertEndsAfterStart($startsAt, $endsAt);

        if ($this->maintenanceStateService->overlapsPendingSchedule($startsAt, $endsAt, (int) $schedule->id)) {
            throw ValidationException::withMessages([
                'starts_at' => ['Overlaps an existing maintenance schedule.'],
            ]);
        }

        $attributes = [];
        if (isset($validated['starts_at'])) {
            $attributes['starts_at'] = $startsAt;
        }
        if (array_key_exists('ends_at', $validated)) {
            $attributes['ends_at'] = $endsAt;
        }
        if (array_key_exists('message', $validated)) {
            $attributes['message'] = $validated['message'];
        }
        if (array_key_exists('mode', $validated)) {
            $attributes['mode'] = $validated['mode'];
        }

        $schedule->update($attributes);

        $schedule->refresh();

        $this->maintenanceStateService->writeAudit((int) auth()->id(), 'schedule_update', [
            'schedule_id' => $schedule->id,
        ]);

        return response()->json(['data' => $schedule]);
    }

    public function schedulesCancel(MaintenanceSchedule $schedule): JsonResponse
    {
        $this->maintenanceStateService->cancelSchedule($schedule, auth()->id() ? (int) auth()->id() : null);

        return response()->json(['data' => ['schedule_id' => $schedule->id, 'cancelled' => true]]);
    }

    public function stateShow(): JsonResponse
    {
        $state = $this->maintenanceStateService->state();

        return response()->json([
            'data' => [
                'is_active' => $state->is_active,
                'mode' => $state->mode,
                'message' => $state->message,
                'ends_at' => $state->ends_at?->toIso8601String(),
                'source' => $state->source,
                'active_schedule_id' => $state->active_schedule_id,
            ],
        ]);
    }

    public function stateUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'mode' => ['required_if:active,true', 'nullable', 'string', 'in:strict,flexible'],
            'message' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $endsAt = null;
        if ($validated['active'] && ! empty($validated['ends_at'])) {
            $endsAt = $this->parseUtcInstant($validated['ends_at']);
        }

        $message = $validated['active'] ? ($validated['message'] ?? '') : '';

        $this->maintenanceStateService->applyManualActive(
            (bool) $validated['active'],
            $validated['mode'] ?? null,
            $message,
            $endsAt,
            'manual',
            null,
            auth()->id() ? (int) auth()->id() : null
        );

        return response()->json(['data' => $this->maintenanceStateService->state()->fresh()]);
    }

    public function auditLogs(): JsonResponse
    {
        $logs = MaintenanceAuditLog::query()
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }

    private function parseUtcInstant(string $value): Carbon
    {
        return Carbon::parse($value, 'UTC');
    }

    private function assertEndsAfterStart(Carbon $startsAt, ?Carbon $endsAt): void
    {
        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => ['Must be after starts_at.'],
            ]);
        }
    }
}
