<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['address_title', 'first_name', 'family_name', 'phone_number', 'user_id', 'address', 'city_id', 'country_id', 'main_address'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * System-generated titles ("عنوان جديد" / "New Address",
     * "العنوان الأساسي" / "Main Address") are stored in the language
     * that was active when the address was created — display them in
     * the current site language. User-typed titles pass through
     * untouched. No data migration: old rows keep their stored value
     * and are translated on read.
     */
    public function getAddressTitleAttribute($value)
    {
        $systemTitles = [
            ['ar' => 'عنوان جديد', 'en' => 'New Address'],
            ['ar' => 'العنوان الأساسي', 'en' => 'Main Address'],
        ];

        foreach ($systemTitles as $pair) {
            if ($value === $pair['ar'] || $value === $pair['en']) {
                return app()->getLocale() === 'en' ? $pair['en'] : $pair['ar'];
            }
        }

        return $value;
    }

}
