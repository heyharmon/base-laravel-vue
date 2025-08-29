<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Events\ArticleChatCreated;

class Chat extends Model
{
	use HasFactory;

        protected $fillable = [
                'role', // The role (user, assistant, system)
                'content', // The content of the chat
                'metadata', // Any additional metadata
                'annotations', // Web search annotations/citations
                'provider',
                'model',
                'usage',
                'cost',
                'price',
        ];

        protected $casts = [
                'metadata' => 'array',
                'annotations' => 'array',
                'usage' => 'array',
                'cost' => 'float',
                'price' => 'float',
        ];

	/**
	 * Boot the model.
	 */
	protected static function boot()
	{
		parent::boot();

		static::created(function ($chat) {
			$conversation = $chat->conversation;

			if ($conversation && $conversation->conversable_type === 'App\\Models\\Article') {
				$article = $conversation->conversable;
				event(new ArticleChatCreated($article, $chat));
			}
		});
	}

	public function conversation(): BelongsTo
	{
		return $this->belongsTo(Conversation::class);
	}
}
