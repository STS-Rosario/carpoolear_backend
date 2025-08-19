<?php

namespace STS\Http\Controllers\Api\Admin;

use STS\Http\Controllers\Controller;
use STS\Http\Requests\BadgeRequest;
use STS\Http\Resources\BadgeResource;
use STS\Models\Badge;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BadgeController extends Controller
{
    /**
     * Display a listing of the badges.
     */
    public function index(): AnonymousResourceCollection
    {
        $badges = Badge::withCount('users')->get();
        return BadgeResource::collection($badges);
    }

    /**
     * Store a newly created badge in storage.
     */
    public function store(BadgeRequest $request): BadgeResource
    {
        $badge = Badge::create($request->validated());
        return new BadgeResource($badge);
    }

    /**
     * Display the specified badge.
     */
    public function show(Badge $badge): BadgeResource
    {
        return new BadgeResource($badge->loadCount('users'));
    }

    /**
     * Update the specified badge in storage.
     */
    public function update(BadgeRequest $request, Badge $badge): BadgeResource
    {
        $badge->update($request->validated());
        return new BadgeResource($badge);
    }

    /**
     * Remove the specified badge from storage.
     */
    public function destroy(Badge $badge): Response
    {
        $badge->delete();
        return response()->noContent();
    }
}
