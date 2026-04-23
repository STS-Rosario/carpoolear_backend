<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Services\ReferenceDeletionService;
use STS\Models\AdminActionLog;
use STS\Models\User;
use STS\Models\References;

class AdminReferenceController extends Controller
{
    protected $referenceDeletionService;

    public function __construct(ReferenceDeletionService $referenceDeletionService)
    {
        $this->referenceDeletionService = $referenceDeletionService;
    }

    public function index(Request $request, User $user): JsonResponse
    {
        $references = $this->referenceDeletionService->getUserReferences($user);
        return response()->json(['references' => $references]);
    }

   

public function deleteReference(Request $request, User $user, $referenceId): JsonResponse
{
    $admin = auth()->user();

    $reference = $user->referencesReceived()
        ->where('id', $referenceId)
        ->firstOrFail();

    $this->referenceDeletionService->deleteReference($reference);

    return response()->json([
        'message' => 'Referencia eliminada correctamente'
    ]);
}
   
}
