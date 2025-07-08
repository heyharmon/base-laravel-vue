<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use App\Models\Chat;
use App\Models\Article;

class ArticleChatCreated implements ShouldBroadcastNow
{
	use Dispatchable, InteractsWithSockets, SerializesModels;

	public function __construct(
		public Article $article,
		public Chat $chat
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
			'id' => $this->chat->id,
			'conversation_id' => $this->chat->conversation_id,
			'role' => $this->chat->role,
			'content' => $this->chat->content,
			// 'metadata' => $this->chat->metadata,
			'annotations' => $this->chat->annotations,
			'created_at' => $this->chat->created_at,
			'updated_at' => $this->chat->updated_at,
		];
	}
}
