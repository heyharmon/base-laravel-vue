<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
