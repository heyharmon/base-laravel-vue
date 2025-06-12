<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasJobStatus;
use App\Events\ArticleUpdated;

class Article extends Model
{
	use HasFactory, HasJobStatus;

	protected $fillable = [
		'team_id',
		'organization_id',
		'prompt_id',
		'title',
		'meta_title',
		'meta_description',
		'schema',
		'outline',
		'content',
	];

	// protected $dispatchesEvents = [
	// 	'updated' => ArticleUpdated::class,
	// ];

	public function team(): BelongsTo
	{
		return $this->belongsTo(Team::class);
	}

	public function organization(): BelongsTo
	{
		return $this->belongsTo(Organization::class);
	}

	public function prompt(): BelongsTo
	{
		return $this->belongsTo(Prompt::class);
	}

	public function conversations(): MorphMany
	{
		return $this->morphMany(Conversation::class, 'conversable');
	}
}
