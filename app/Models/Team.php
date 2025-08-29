<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\JobStatus;
use App\Models\Campaign;
use App\Models\Response;

class Team extends Model
{
	use HasFactory;

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
        protected $fillable = [
                'name',
                'owner_id',
                'responses_limit',
                'articles_limit',
        ];

	/**
	 * Get the user that owns the team.
	 */
	public function owner(): BelongsTo
	{
		return $this->belongsTo(User::class, 'owner_id');
	}

	/**
	 * Get the users that belong to the team.
	 */
	public function users(): BelongsToMany
	{
		return $this->belongsToMany(User::class)
			->withPivot('role', 'invitation_accepted', 'invitation_sent_at', 'joined_at')
			->withTimestamps();
	}

	/**
	 * Get the members of the team (users who have accepted the invitation).
	 */
	public function members(): BelongsToMany
	{
		return $this->users()->wherePivot('invitation_accepted', true);
	}

	/**
	 * Get the pending invitations for the team.
	 */
	public function pendingInvitations(): BelongsToMany
	{
		return $this->users()->wherePivot('invitation_accepted', false);
	}

	/**
	 * Get the terms that belong to the team.
	 */
	public function terms(): HasMany
	{
		return $this->hasMany(Term::class);
	}

	/**
	 * Get the prompts that belong to the team.
	 */
	public function prompts(): HasMany
	{
		return $this->hasMany(Prompt::class);
	}

	/**
	 * Get the organizations that belong to the team.
	 */
	public function organizations(): HasMany
	{
		return $this->hasMany(Organization::class);
	}

	/**
	 * Get the articles that belong to the team.
	 */
	public function articles(): HasMany
	{
		return $this->hasMany(Article::class);
	}

	/**
	 * Get the campaigns that belong to the team.
	 */
	public function campaigns(): HasMany
	{
		return $this->hasMany(Campaign::class);
	}

	/**
	 * Get the default campaign for the team.
	 */
	public function defaultCampaign()
	{
		return $this->campaigns()->where('is_default', true)->first();
	}

	/**
	 * Get the conversations that belong to the team.
	 */
	public function conversations(): HasMany
	{
		return $this->hasMany(Conversation::class);
	}

	/**
	 * Get the job statuses that belong to the team.
	 */
        public function jobStatuses(): HasMany
        {
                return $this->hasMany(JobStatus::class);
        }

        /**
         * Get responses used in period.
         */
        public function responsesUsed($start = null, $end = null): int
        {
                $start = $start ? \Carbon\Carbon::parse($start) : now()->startOfMonth();
                $end = $end ? \Carbon\Carbon::parse($end) : now()->endOfMonth();

                return Response::whereHas('prompt', function ($q) {
                        $q->where('team_id', $this->id);
                })->whereBetween('created_at', [$start, $end])->count();
        }

        /**
         * Get articles used in period.
         */
        public function articlesUsed($start = null, $end = null): int
        {
                $start = $start ? \Carbon\Carbon::parse($start) : now()->startOfMonth();
                $end = $end ? \Carbon\Carbon::parse($end) : now()->endOfMonth();

                return $this->articles()->whereBetween('created_at', [$start, $end])->count();
        }

        public function responsesRemaining($start = null, $end = null): ?int
        {
                if (is_null($this->responses_limit)) {
                        return null;
                }

                $used = $this->responsesUsed($start, $end);
                return max(0, $this->responses_limit - $used);
        }

        public function articlesRemaining($start = null, $end = null): ?int
        {
                if (is_null($this->articles_limit)) {
                        return null;
                }

                $used = $this->articlesUsed($start, $end);
                return max(0, $this->articles_limit - $used);
        }
}
