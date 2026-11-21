<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'status',
        'phone_code'
    ];
    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /**
     * The store is Egypt-only: the id of the Egypt country row.
     * Falls back to the sole country row when exactly one exists
     * (e.g. a seed without the EG code); null when it cannot be
     * determined. Never hardcode a country id.
     */
    public static function egyptId(): ?int
    {
        $egyptId = static::where('code', 'EG')->value('id');

        if ($egyptId) {
            return (int) $egyptId;
        }

        $ids = static::pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
    public $timestamps = false;
    
    protected $appends = ['name'];

    public function getNameAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' || empty($this->name_en)) {
            return $this->name_ar;
        }
        return $this->name_en;
    }
}
