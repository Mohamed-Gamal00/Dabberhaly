<?php

return [
    'egyptian_mobile' => 'The phone number must be a valid Egyptian mobile number starting with 010, 011, 012 or 015.',
    'register' => [
        'first_name_required'   => 'First name is required.',
        'first_name_min'        => 'First name must be at least 2 characters.',
        'first_name_max'        => 'First name must not exceed 50 characters.',
        'first_name_regex'      => 'First name may only contain letters.',

        'family_name_required'  => 'Last name is required.',
        'family_name_min'       => 'Last name must be at least 2 characters.',
        'family_name_max'       => 'Last name must not exceed 50 characters.',
        'family_name_regex'     => 'Last name may only contain letters.',

        'email_required'        => 'Email address is required.',
        'email_invalid'         => 'Please enter a valid email address.',
        'email_taken'           => 'This email address is already registered.',

        'phone_required'        => 'Phone number is required.',
        'phone_taken'           => 'This phone number is already registered.',

        'address_required'      => 'Address is required.',
        'address_min'           => 'Address must be at least 5 characters.',

        'country_required'      => 'Country is required.',
        'country_invalid'       => 'The selected country is invalid.',

        'city_required'         => 'City is required.',
        'city_invalid'          => 'The selected city is invalid.',

        'password_required'     => 'Password is required.',
        'password_confirmed'    => 'Password and confirmation do not match.',
        'password_min'          => 'Password must be at least 8 characters.',
        'password_mixed_case'   => 'Password must contain both uppercase and lowercase letters.',
        'password_numbers'      => 'Password must contain at least one number.',
    ],
];
