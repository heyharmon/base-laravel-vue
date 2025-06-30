<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationIndustry extends Model
{
    protected $table = 'organization_industries';
    
    protected $guarded = [
        'id'
    ];

    /**
     * Get the organizations that belong to this industry.
     */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
