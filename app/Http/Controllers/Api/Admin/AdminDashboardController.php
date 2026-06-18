<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;
use STS\Models\SupportTicket;

class AdminDashboardController extends Controller
{
    public function show(): JsonResponse
    {
        $manualIdentityValidations = ManualIdentityValidation::query()
            ->with('user:id,name')
            ->readyForAdminReview()
            ->orderByRaw('COALESCE(submitted_at, paid_at, created_at) ASC')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (ManualIdentityValidation $item) => $this->mapManualIdentityValidation($item));

        $supportTickets = SupportTicket::query()
            ->with('user:id,name')
            ->adminNeedsAttention()
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit(10)
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->mapSupportTicket($ticket));

        return response()->json([
            'data' => [
                'manual_identity_validations' => $manualIdentityValidations,
                'support_tickets' => $supportTickets,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapManualIdentityValidation(ManualIdentityValidation $item): array
    {
        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user_name' => $item->user?->name,
            'submitted_at' => $item->submitted_at?->toDateTimeString(),
            'paid_at' => $item->paid_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSupportTicket(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'user_name' => $ticket->user?->name,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
        ];
    }
}
