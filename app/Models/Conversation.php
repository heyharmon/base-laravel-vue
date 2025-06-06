<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Team;

class Conversation extends Model
{
	use HasFactory;

	protected $fillable = [
		'team_id',
		'title',
	];

	public function team(): BelongsTo
	{
		return $this->belongsTo(Team::class);
	}

	public function chats(): HasMany
	{
		return $this->hasMany(Chat::class);
	}
}
