<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\AdminActionLog;
use STS\Models\References;
use STS\Services\AdminActionLogger;
use STS\Transformers\ReferenceTransformer;

class ReferencesController extends Controller
{
    public function update(Request $request, int $reference): JsonResponse
    {
        if (! auth()->user()?->is_admin) {
            return response()->json('Unauthorized.', 401);
        }

        $model = References::query()->find($reference);
        if (! $model) {
            return response()->json(['message' => 'Reference not found.'], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string',
        ]);

        $before = [
            'comment' => $model->comment,
        ];

        $model->comment = $validated['comment'];
        $model->save();

        $after = [
            'comment' => $model->comment,
        ];

        AdminActionLogger::log(
            auth()->user(),
            AdminActionLog::ACTION_REFERENCE_UPDATE,
            $model->user_id_to,
            [
                'entity_id' => $model->id,
                'entity_type' => 'reference',
                'before' => $before,
                'after' => $after,
            ]
        );

        return $this->item($model->fresh(), new ReferenceTransformer);
    }
}
