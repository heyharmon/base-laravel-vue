<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'google_place_id',
        'banner',
        'score',
        'reviews',
        'street',
        'city',
        'state',
        'country_code',
        'website',
        'phone',
        'category',
        'map_url',
        'notes',
    ];

    protected $casts = [
        'score' => 'decimal:1',
        'reviews' => 'integer',
    ];

    public function getFullAddressAttribute()
    {
        $parts = array_filter([$this->street, $this->city, $this->state]);
        return implode(', ', $parts);
    }

    public function getFormattedWebsiteAttribute()
    {
        if (!$this->website) return null;
        if (!str_starts_with($this->website, 'http')) {
            return 'https://' . $this->website;
        }
        return $this->website;
    }

    public static function findByGooglePlaceId($placeId)
    {
        return static::withTrashed()->where('google_place_id', $placeId)->first();
    }

    public function scopeByLocation($query, $city = null, $state = null)
    {
        if ($city) {
            $query->where('city', 'LIKE', "%{$city}%");
        }
        if ($state) {
            $query->where('state', 'LIKE', "%{$state}%");
        }
        return $query;
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', 'LIKE', "%{$category}%");
    }
}
