<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Http\Controllers\Controller;
use STS\Models\TripCreationTemplate;

class TripCreationTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('logged');
    }

    public function index()
    {
        $templates = TripCreationTemplate::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->get()
            ->map(fn (TripCreationTemplate $template) => [
                'name' => $template->name,
                'data' => $template->data,
            ])
            ->values();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
        ]);

        $name = trim($validated['name']);
        if ($name === '') {
            return response()->json(['message' => 'The name field is required.'], 422);
        }

        $template = TripCreationTemplate::query()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'name' => $name,
            ],
            [
                'data' => $validated['data'],
            ]
        );

        return response()->json([
            'data' => [
                'name' => $template->name,
                'data' => $template->data,
            ],
        ]);
    }

    public function show(string $name)
    {
        $template = TripCreationTemplate::query()
            ->where('user_id', auth()->id())
            ->where('name', urldecode($name))
            ->first();

        if ($template === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json([
            'data' => [
                'name' => $template->name,
                'data' => $template->data,
            ],
        ]);
    }
}
