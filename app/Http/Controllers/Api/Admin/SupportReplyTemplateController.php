<?php

namespace STS\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Http\Controllers\Controller;
use STS\Models\SupportReplyTemplate;

class SupportReplyTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = SupportReplyTemplate::query()
            ->with([
                'creator:id,name',
                'updater:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (SupportReplyTemplate $t) => $this->serializeTemplate($t))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:255',
            'short_description' => 'nullable|string|max:2000',
            'body_markdown' => 'required|string|min:1',
        ]);

        $admin = auth()->user();
        $template = SupportReplyTemplate::create([
            'name' => $validated['name'],
            'short_description' => $validated['short_description'] ?? null,
            'body_markdown' => $validated['body_markdown'],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $template->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeTemplate($template)], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $template = SupportReplyTemplate::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->findOrFail($id);

        return response()->json(['data' => $this->serializeTemplate($template)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:255',
            'short_description' => 'nullable|string|max:2000',
            'body_markdown' => 'required|string|min:1',
        ]);

        $template = SupportReplyTemplate::query()->findOrFail($id);
        $admin = auth()->user();

        $template->fill([
            'name' => $validated['name'],
            'short_description' => $validated['short_description'] ?? null,
            'body_markdown' => $validated['body_markdown'],
            'updated_by' => $admin->id,
        ]);
        $template->save();

        $template->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeTemplate($template)]);
    }

    public function destroy(int $id): Response
    {
        $template = SupportReplyTemplate::query()->findOrFail($id);
        $template->delete();

        return response()->noContent();
    }

    public function duplicate(int $id): JsonResponse
    {
        $original = SupportReplyTemplate::query()->findOrFail($id);
        $admin = auth()->user();

        $copy = SupportReplyTemplate::create([
            'name' => $original->name.' (copy)',
            'short_description' => $original->short_description,
            'body_markdown' => $original->body_markdown,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $copy->load(['creator:id,name', 'updater:id,name']);

        return response()->json(['data' => $this->serializeTemplate($copy)], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(SupportReplyTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'short_description' => $template->short_description,
            'body_markdown' => $template->body_markdown,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'created_by' => $template->created_by,
            'updated_by' => $template->updated_by,
            'creator' => $template->relationLoaded('creator') && $template->creator
                ? ['id' => $template->creator->id, 'name' => $template->creator->name]
                : null,
            'updater' => $template->relationLoaded('updater') && $template->updater
                ? ['id' => $template->updater->id, 'name' => $template->updater->name]
                : null,
        ];
    }
}
