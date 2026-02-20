<?php

namespace STS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBadgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Public-facing badge data for user profile display.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image_path' => $this->image_path,
        ];
    }
}
