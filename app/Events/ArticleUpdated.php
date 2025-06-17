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
	}
}
