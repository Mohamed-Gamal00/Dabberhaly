<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_name',
        'website_name_en',
        'subscription_title',
        'phone',
        'image',
        'facebook',
        'twitter',
        'description',
        'address',
        'email',
        'google_play',
        'apple_store',
        'logo',
        'instagram',
        'phone_number',
        'snap',
        'tiktok',
        'tax_number',
        'value_added_tax',
        'free_shipping_threshold',
        'payment_cod_enabled',
        'payment_card_enabled',
        'publishable_key',
        'secret_key',
        'sms_api_key',
        'sms_user_name',
        'sms_sender',

        // SEO
        'seo_meta_title',
        'seo_meta_description',
        'seo_meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'google_analytics_id',
        'google_tag_manager_id',
        'google_site_verification',
        'canonical_url',
        'robots_index',
    ];

    protected $casts = [
        // Secrets are stored encrypted at rest, never as plaintext.
        'secret_key' => 'encrypted',
        'sms_api_key' => 'encrypted',
        'publishable_key' => 'encrypted',
        'payment_cod_enabled' => 'boolean',
        'payment_card_enabled' => 'boolean',
    ];

    /**
     * Payment methods the admin enabled in settings, as the values
     * the checkout uses ('cash_on_delivery', 'card_payment').
     * Never empty: with no settings row both are on, and if both
     * were somehow switched off the store falls back to cash on
     * delivery so checkout never ends up with no way to pay.
     */
    public static function enabledPaymentMethods(): array
    {
        $settings = static::first();

        $methods = [];
        if ($settings?->payment_cod_enabled ?? true) {
            $methods[] = 'cash_on_delivery';
        }
        if ($settings?->payment_card_enabled ?? true) {
            $methods[] = 'card_payment';
        }

        return $methods ?: ['cash_on_delivery'];
    }

    /**
     * Values accepted as `payment_method` in checkout validation.
     * Same as enabledPaymentMethods(), plus the legacy 'cash' alias
     * the mobile API sends for cash on delivery.
     */
    public static function allowedPaymentMethodValues(): array
    {
        $allowed = static::enabledPaymentMethods();

        if (in_array('cash_on_delivery', $allowed, true)) {
            $allowed[] = 'cash';
        }

        return $allowed;
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return asset('assets/images/logo.jpg');
        }
        return asset('storage/' . $this->image);
    }

    public function getCurrentNameLangAttribute()
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' || empty($this->website_name_en)) {
            return $this->website_name;
        }
        return $this->website_name_en;
    }
}
