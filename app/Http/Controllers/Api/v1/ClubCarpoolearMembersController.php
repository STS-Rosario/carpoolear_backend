<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\User;
use STS\Services\ClubCarpoolearMembershipService;

class ClubCarpoolearMembersController extends Controller
{
    public function __construct(private ClubCarpoolearMembershipService $clubCarpoolearMembershipService) {}

    public function index(): JsonResponse
    {
        $members = User::query()
            ->where('monthly_donate', true)
            ->where('show_club_carpoolear_membership', true)
            ->whereNotNull('club_carpoolear_joined_at')
            ->whereNull('club_carpoolear_left_at')
            ->orderBy('club_carpoolear_joined_at')
            ->get(['id', 'name', 'image', 'club_carpoolear_joined_at']);

        return response()->json([
            'data' => $members->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'image' => $user->image,
                'club_carpoolear_joined_at' => $user->club_carpoolear_joined_at?->toDateTimeString(),
            ])->values()->all(),
        ]);
    }

    public function markWelcomeShown(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->clubCarpoolearMembershipService->markWelcomeShown($user);

        return response()->json([
            'club_carpoolear_welcome_shown' => true,
        ]);
    }
}
