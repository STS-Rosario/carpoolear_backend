<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Http\Controllers\Controller;
use STS\Models\Changelog;

class ChangelogController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Changelog::query()->get();

        $sorted = $rows->sort(function (Changelog $left, Changelog $right) {
            return version_compare($right->version, $left->version);
        })->values();

        return response()->json([
            'data' => $sorted->map(fn (Changelog $changelog) => [
                'id' => $changelog->id,
                'version' => $changelog->version,
                'body_markdown' => $changelog->body_markdown,
            ])->all(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|min:1|max:32',
        ]);

        $changelog = Changelog::query()
            ->where('version', $validated['version'])
            ->first();

        if (! $changelog) {
            return response()->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $changelog->id,
                'version' => $changelog->version,
                'body_markdown' => $changelog->body_markdown,
            ],
        ]);
    }
}
