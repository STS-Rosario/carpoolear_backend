<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Models\ManualIdentityValidation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManualIdentityValidationController extends Controller
{
    /**
     * GET /api/admin/manual-identity-validations - list, paid first then unpaid, by submitted_at asc (oldest first)
     */
    public function index(): JsonResponse
    {
        $items = ManualIdentityValidation::with('user:id,name')
            ->orderByRaw('CASE WHEN paid = 1 THEN 0 ELSE 1 END')
            ->orderBy('submitted_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'user_name' => $item->user ? $item->user->name : null,
                    'paid_at' => $item->paid_at ? $item->paid_at->toDateTimeString() : null,
                    'submitted_at' => $item->submitted_at ? $item->submitted_at->toDateTimeString() : null,
                    'paid' => $item->paid,
                    'review_status' => $item->review_status,
                    'has_images' => $item->hasImages(),
                ];
            });

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/admin/manual-identity-validations/{id} - single request with user (name, nro_doc) and image URLs (for frontend to load via image endpoint)
     */
    public function show(int $id): JsonResponse
    {
        $item = ManualIdentityValidation::with('user:id,name,nro_doc')->findOrFail($id);

        $baseUrl = rtrim(config('app.url'), '/');
        $imageUrl = fn ($type) => $baseUrl . '/api/admin/manual-identity-validations/' . $id . '/image/' . $type;

        return response()->json([
            'data' => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'user_name' => $item->user ? $item->user->name : null,
                'user_nro_doc' => $item->user ? $item->user->nro_doc : null,
                'paid_at' => $item->paid_at ? $item->paid_at->toDateTimeString() : null,
                'submitted_at' => $item->submitted_at ? $item->submitted_at->toDateTimeString() : null,
                'paid' => $item->paid,
                'review_status' => $item->review_status,
                'review_note' => $item->review_note,
                'reviewed_at' => $item->reviewed_at ? $item->reviewed_at->toDateTimeString() : null,
                'reviewed_by' => $item->reviewed_by,
                'front_image_url' => $item->front_image_path ? $imageUrl('front') : null,
                'back_image_url' => $item->back_image_path ? $imageUrl('back') : null,
                'selfie_image_url' => $item->selfie_image_path ? $imageUrl('selfie') : null,
                'has_images' => $item->hasImages(),
            ],
        ]);
    }

    /**
     * GET /api/admin/manual-identity-validations/{id}/image/{type} - stream image (front|back|selfie)
     */
    public function image(int $id, string $type): StreamedResponse|JsonResponse
    {
        $allowed = ['front', 'back', 'selfie'];
        if (!in_array($type, $allowed, true)) {
            return response()->json(['error' => 'Invalid image type'], 404);
        }

        $item = ManualIdentityValidation::findOrFail($id);
        $pathColumn = $type === 'front' ? 'front_image_path' : ($type === 'back' ? 'back_image_path' : 'selfie_image_path');
        $path = $item->$pathColumn;

        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        $mime = Storage::disk('local')->mimeType($path) ?: 'image/jpeg';
        return response()->stream(function () use ($path) {
            $stream = Storage::disk('local')->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
        ]);
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/review - action: approve|reject|pending, note: string (required)
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,pending',
            'note' => 'required|string|min:1',
        ]);

        $item = ManualIdentityValidation::with('user')->findOrFail($id);

        if (!$item->paid) {
            return response()->json(['error' => 'Unpaid request cannot be reviewed'], 422);
        }

        $admin = auth()->user();
        $item->review_status = $validated['action'];
        $item->reviewed_by = $admin->id;
        $item->reviewed_at = now();
        $item->review_note = $validated['note'];
        $item->save();

        if ($validated['action'] === 'approve') {
            $user = $item->user;
            $user->identity_validated = true;
            $user->identity_validated_at = now();
            $user->identity_validation_type = 'manual';
            $user->save();
        }

        return response()->json(['data' => $item->fresh(['user:id,name,nro_doc'])]);
    }

    /**
     * POST /api/admin/manual-identity-validations/{id}/purge - delete only image files, clear paths
     */
    public function purge(int $id): JsonResponse
    {
        $item = ManualIdentityValidation::findOrFail($id);

        foreach (['front_image_path', 'back_image_path', 'selfie_image_path'] as $col) {
            $path = $item->$col;
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
            $item->$col = null;
        }
        $item->save();

        return response()->json(['message' => 'Photos purged', 'data' => $item->fresh()]);
    }
}
