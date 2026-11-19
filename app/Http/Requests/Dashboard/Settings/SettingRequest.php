<?php

namespace App\Http\Requests\Dashboard\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'website_name'             => 'required',
            'website_name_en'          => 'nullable',
            'subscription_title'       => 'nullable',
            'image'                    => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'logo'                     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'address'                  => 'nullable',
            'phone_number'             => ['nullable', 'numeric'],
            'value_added_tax'          => ['nullable', 'numeric'],
            'free_shipping_threshold'  => ['nullable', 'numeric', 'min:0'],
            'payment_cod_enabled'      => ['nullable', 'boolean'],
            'payment_card_enabled'     => ['nullable', 'boolean'],
            'tax_number'               => ['nullable', 'numeric'],
            'email'                    => 'nullable|email',
            'facebook'                 => 'nullable',
            'twitter'                  => 'nullable',
            'instagram'                => 'nullable',
            'snap'                     => 'nullable',
            'tiktok'                   => 'nullable',
            'publishable_key'          => 'nullable',
            'secret_key'               => 'nullable',
            'sms_api_key'              => 'nullable',
            'sms_user_name'            => 'nullable',
            'sms_sender'               => 'nullable',
            'apple_store'              => 'nullable',
            'google_play'              => 'nullable',

            // SEO
            'seo_meta_title'           => 'nullable|string|max:160',
            'seo_meta_description'     => 'nullable|string|max:320',
            'seo_meta_keywords'        => 'nullable|string|max:500',
            'og_title'                 => 'nullable|string|max:160',
            'og_description'           => 'nullable|string|max:320',
            'og_image'                 => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'twitter_card'             => ['nullable', Rule::in(['summary', 'summary_large_image'])],
            'twitter_title'            => 'nullable|string|max:160',
            'twitter_description'      => 'nullable|string|max:320',
            'twitter_image'            => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'google_analytics_id'      => 'nullable|string|max:50',
            'google_tag_manager_id'    => 'nullable|string|max:50',
            'google_site_verification' => 'nullable|string|max:200',
            'canonical_url'            => 'nullable|url',
            'robots_index'             => ['nullable', Rule::in([
                'index,follow',
                'noindex,nofollow',
                'index,nofollow',
                'noindex,follow',
            ])],
        ];

        return $rules;
    }

    /**
     * At least one payment method must stay visible: hiding both
     * would leave checkout with no way to pay. Only enforced when
     * the toggles are actually submitted (other settings updates
     * that don't touch them are unaffected).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('payment_cod_enabled') && ! $this->has('payment_card_enabled')) {
                return;
            }

            if (! $this->boolean('payment_cod_enabled') && ! $this->boolean('payment_card_enabled')) {
                $validator->errors()->add(
                    'payment_card_enabled',
                    'يجب إظهار طريقة دفع واحدة على الأقل (الدفع عند الاستلام أو الدفع بالبطاقة).'
                );
            }
        });
    }
}
