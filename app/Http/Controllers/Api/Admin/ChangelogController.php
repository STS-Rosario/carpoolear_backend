<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use STS\Http\Controllers\Controller;
use STS\Models\Changelog;

class ChangelogController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Changelog::query()
            ->with([
                'creator:id,name',
                'updater:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Changelog $c) => $this->serializeChangelog($c))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'required|string|min:1|max:32|unique:changelogs,version',
            'body_markdown' => 'required|string|min:1',
        ]);

        $admin = auth()->user();
        $changelog = Changelog::create([
            'version' => $validated['version'],
            'body_markdown' => $validated['body_markdown'],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $changelog->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeChangelog($changelog)], Response::HTTP_CREATED);
    }

    public function show(Changelog $changelog): JsonResponse
    {
        $changelog->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeChangelog($changelog)]);
    }

    public function update(Request $request, Changelog $changelog): JsonResponse
    {
        $validated = $request->validate([
            'version' => [
                'required',
                'string',
                'min:1',
                'max:32',
                Rule::unique('changelogs', 'version')->ignore($changelog->id),
            ],
            'body_markdown' => 'required|string|min:1',
        ]);

        $admin = auth()->user();

        $changelog->fill([
            'version' => $validated['version'],
            'body_markdown' => $validated['body_markdown'],
            'updated_by' => $admin->id,
        ]);
        $changelog->save();

        $changelog->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeChangelog($changelog)]);
    }

    public function destroy(Changelog $changelog): Response
    {
        $changelog->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChangelog(Changelog $changelog): array
    {
        return [
            'id' => $changelog->id,
            'version' => $changelog->version,
            'body_markdown' => $changelog->body_markdown,
            'created_at' => $changelog->created_at?->toIso8601String(),
            'updated_at' => $changelog->updated_at?->toIso8601String(),
            'created_by' => $changelog->created_by,
            'updated_by' => $changelog->updated_by,
            'creator' => $changelog->relationLoaded('creator') && $changelog->creator
                ? ['id' => $changelog->creator->id, 'name' => $changelog->creator->name]
                : null,
            'updater' => $changelog->relationLoaded('updater') && $changelog->updater
                ? ['id' => $changelog->updater->id, 'name' => $changelog->updater->name]
                : null,
        ];
    }
}
