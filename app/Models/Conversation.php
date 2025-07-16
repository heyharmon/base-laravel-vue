<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\BelongsToTeam;
use App\Models\Team;

class Conversation extends Model
{
	use HasFactory, BelongsToTeam;

	protected $fillable = [
		'team_id',
		'openai_response_id',
		'title',
		'conversable_type',
		'conversable_id',
	];

	public function chats(): HasMany
	{
		return $this->hasMany(Chat::class);
	}

	public function conversable(): MorphTo
	{
		return $this->morphTo();
	}
}
