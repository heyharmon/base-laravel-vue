<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_team_id',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Get the teams owned by the user.
     */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    /**
     * Get all teams that the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('role', 'invitation_accepted', 'invitation_sent_at', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get the teams that the user is a member of (accepted invitations).
     */
    public function joinedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('invitation_accepted', true);
    }

    public function isSuperAdmin()
    {
        return $this->is_super_admin;
    }

    /**
     * Get the pending team invitations for the user.
     */
    public function pendingTeamInvitations(): BelongsToMany
    {
        return $this->teams()->wherePivot('invitation_accepted', false);
    }
    
    /**
     * Get the user's current team.
     */
    public function currentTeam()
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }
}
