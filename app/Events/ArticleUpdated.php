<?php

namespace App\Events;

use App\Models\Article;
use App\Http\Resources\Article\ArticleResource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleUpdated implements ShouldBroadcastNow
{
	use Dispatchable, InteractsWithSockets, SerializesModels;

	/**
	 * Create a new event instance.
	 */
	public function __construct(
		public Article $article
	) {}

	/**
	 * Get the channels the event should broadcast on.
	 *
	 * @return array<int, \Illuminate\Broadcasting\Channel>
	 */
	public function broadcastOn(): array
	{
		return [
			new PrivateChannel('article.' . $this->article->id),
		];
	}

	/**
	 * Get the data to broadcast.
	 *
	 * @return array
	 */
	public function broadcastWith(): array
	{
		// Load the versions relationship to ensure it's included in the resource
		$this->article->load('versions');

		return (new ArticleResource($this->article))->resolve();

		// Create a lightweight version of the article without the full content
		// return [
		// 	'id' => $this->article->id,
		// 	'team_id' => $this->article->team_id,
		// 	'organization_id' => $this->article->organization_id,
		// 	'title' => $this->article->title,
		// 	'current_version' => $this->article->current_version,
		// 	'updated_at' => $this->article->updated_at,
		// 	'created_at' => $this->article->created_at,
		// 	// Include a content_updated flag instead of the full content
		// 	'content_updated' => true,
		// 	// Include versions but not the full article content
		// 	'versions' => $this->article->versions->map(function ($version) {
		// 		return [
		// 			'id' => $version->id,
		// 			'version_number' => $version->version_number,
		// 			'created_at' => $version->created_at
		// 		];
		// 	})
		// ];
	}
}
