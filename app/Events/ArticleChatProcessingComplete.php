<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use App\Models\Article;
use App\Models\Conversation;

// Event broadcasted when article chat processing is complete
class ArticleChatProcessingComplete implements ShouldBroadcastNow
{
	use Dispatchable, InteractsWithSockets, SerializesModels;

	public function __construct(
		public Article $article,
		public Conversation $conversation,
		public bool $success = true,
		public ?string $error = null
	) {}

	/**
	 * Get the channels the event should broadcast on.
	 */
	public function broadcastOn(): array
	{
		return [
			new PrivateChannel('article.' . $this->article->id)
		];
	}

	/**
	 * Get the data to broadcast.
	 */
	public function broadcastWith(): array
	{
		return [
			'conversation_id' => $this->conversation->id,
			'success' => $this->success,
			'error' => $this->error,
			'completed_at' => now()->toISOString(),
		];
	}
}
