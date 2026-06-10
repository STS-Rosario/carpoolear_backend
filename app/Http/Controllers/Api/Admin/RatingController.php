<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\AdminActionLog;
use STS\Models\Rating;
use STS\Models\User;
use STS\Repository\RatingRepository;
use STS\Services\AdminActionLogger;
use STS\Transformers\AdminRatingTransformer;

class RatingController extends Controller
{
    public function __construct(protected RatingRepository $ratingRepository) {}

    public function index(User $user): JsonResponse
    {
        if (! auth()->user()?->is_admin) {
            return response()->json('Unauthorized.', 401);
        }

        $transformer = new AdminRatingTransformer(auth()->user());
        $received = $this->ratingRepository->getReceivedRatingsForUser($user->id)
            ->map(fn (Rating $rating) => $transformer->transform($rating))
            ->values();
        $given = $this->ratingRepository->getGivenRatingsByUser($user->id)
            ->map(fn (Rating $rating) => $transformer->transform($rating))
            ->values();

        return response()->json([
            'data' => [
                'received' => $received,
                'given' => $given,
            ],
        ]);
    }

    public function update(Request $request, int $rating): JsonResponse
    {
        if (! auth()->user()?->is_admin) {
            return response()->json('Unauthorized.', 401);
        }

        $model = Rating::query()->find($rating);
        if (! $model) {
            return response()->json(['message' => 'Rating not found.'], 404);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|integer|in:0,1,2',
            'comment' => 'nullable|string',
            'reply_comment' => 'nullable|string',
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'No fields to update.'], 422);
        }

        $before = [
            'rating' => (int) $model->rating,
            'comment' => $model->comment,
            'reply_comment' => $model->reply_comment,
        ];

        $model->fill($validated);
        $model->save();

        $after = [
            'rating' => (int) $model->rating,
            'comment' => $model->comment,
            'reply_comment' => $model->reply_comment,
        ];

        AdminActionLogger::log(
            auth()->user(),
            AdminActionLog::ACTION_RATING_UPDATE,
            $model->user_id_to,
            [
                'entity_id' => $model->id,
                'entity_type' => 'rating',
                'before' => $before,
                'after' => $after,
            ]
        );

        return $this->item(
            $model->fresh(['from', 'to', 'trip']),
            new AdminRatingTransformer(auth()->user())
        );
    }
}
