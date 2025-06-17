<?php

namespace App\Http\Resources\Article;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Article\ArticleVersionResource;

class ArticleResource extends JsonResource
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
            'team_id' => $this->team_id,
            'organization_id' => $this->organization_id,
            'prompt_id' => $this->prompt_id,
            'current_version' => $this->current_version,
            'title' => $this->title,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'schema' => $this->schema,
            'outline' => $this->outline,
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Include relationships when they're loaded
            'organization' => $this->when($this->relationLoaded('organization'), function() {
                return $this->organization;
            }),
            'prompt' => $this->when($this->relationLoaded('prompt'), function() {
                return $this->prompt;
            }),
            'team' => $this->when($this->relationLoaded('team'), function() {
                return $this->team;
            }),
            'versions' => $this->when($this->relationLoaded('versions'), function() {
                return ArticleVersionResource::collection($this->versions);
            }),
            'conversations' => $this->when($this->relationLoaded('conversations'), function() {
                return $this->conversations;
            }),
        ];
    }
}
