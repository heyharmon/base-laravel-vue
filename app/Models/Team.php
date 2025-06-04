<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\JobStatus;

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
         * Get the job statuses that belong to the team.
         */
        public function jobStatuses(): HasMany
        {
                return $this->hasMany(JobStatus::class);
        }

        /**
         * The "booted" method of the model.
         * Remove related data and queued jobs when deleting a team.
         */
        protected static function booted()
        {
                static::deleting(function (Team $team) {
                        // Detach all users from the team
                        $team->users()->detach();

                        // Delete related models via Eloquent so model events fire
                        $team->prompts()->get()->each->delete();
                        $team->terms()->get()->each->delete();
                        $team->organizations()->get()->each->delete();

                        // Remove queued job data
                        $jobIds = $team->jobStatuses()->pluck('job_id')->toArray();
                        $batchIds = $team->jobStatuses()->whereNotNull('job_batch_id')->pluck('job_batch_id')->toArray();

                        // Delete job status records
                        $team->jobStatuses()->delete();

                        if (!empty($jobIds)) {
                                DB::table('jobs')->where(function ($query) use ($jobIds) {
                                        foreach ($jobIds as $id) {
                                                $query->orWhere('payload', 'like', '%' . $id . '%');
                                        }
                                })->delete();

                                DB::table('failed_jobs')->where(function ($query) use ($jobIds) {
                                        foreach ($jobIds as $id) {
                                                $query->orWhere('payload', 'like', '%' . $id . '%');
                                        }
                                })->delete();
                        }

                        if (!empty($batchIds)) {
                                DB::table('job_batches')->whereIn('id', $batchIds)->delete();
                        }
                });
        }
}
