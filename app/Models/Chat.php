<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Events\ChatCreated;

class Chat extends Model
{
	use HasFactory;

	protected $fillable = [
		'role', // The role (user, assistant, system)
		'content', // The content of the chat
		'metadata', // Any additional metadata
		'annotations', // Web search annotations/citations
	];

	protected $casts = [
		'metadata' => 'array',
		'annotations' => 'array',
	];

	/**
	 * The event map for the model.
	 *
	 * @var array
	 */
	protected $dispatchesEvents = [
		'created' => ChatCreated::class,
	];

	public function conversation(): BelongsTo
	{
		return $this->belongsTo(Conversation::class);
	}
}
