<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'job_class',
        'job_batch_id',
        'status',
        'output',
        'error',
        'progress',
        'team_id',
    ];

    /**
     * Get the owning trackable model.
     */
    public function trackable()
    {
        return $this->morphTo();
    }
    
    /**
     * Get the team that owns the job status.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to only include pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include processing jobs.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope a query to only include completed jobs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed jobs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include cancelled jobs.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
