<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatCreated implements ShouldBroadcastNow
{
	use Dispatchable, InteractsWithSockets, SerializesModels;

	public function __construct(
		public Chat $chat
	) {}

	/**
	 * Get the channels the event should broadcast on.
	 */
	public function broadcastOn(): array
	{
		return [
			new PrivateChannel('conversation.' . $this->chat->conversation_id)
		];
	}

	/**
	 * The event's broadcast name.
	 */
	public function broadcastAs(): string
	{
		return 'ChatCreated';
	}

	/**
	 * Get the data to broadcast.
	 */
	public function broadcastWith(): array
	{
		// Load the chat with any necessary relationships
		$this->chat->load(['conversation']);

		return [
			'id' => $this->chat->id,
			'conversation_id' => $this->chat->conversation_id,
			'role' => $this->chat->role,
			'content' => $this->chat->content,
			'metadata' => $this->chat->metadata,
			'annotations' => $this->chat->annotations,
			'created_at' => $this->chat->created_at,
			'updated_at' => $this->chat->updated_at,
		];
	}
}
