<?php

namespace STS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BadgeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'rules' => $this->rules,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
