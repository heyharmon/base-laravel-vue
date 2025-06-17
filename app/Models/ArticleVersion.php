<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Prunable;

class ArticleVersion extends Model
{
	use Prunable;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'article_id',
		'version_number',
		'data',
	];

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'data' => AsCollection::class,
	];

	/**
	 * Get the prunable model query.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public function prunable()
	{
		// Prune versions older than 1 month
		return static::where('created_at', '<=', now()->subMonth());
	}

	/**
	 * Get the article that owns the version.
	 */
	public function article(): BelongsTo
	{
		return $this->belongsTo(Article::class);
	}
}
