<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'first_name' => 'Mohamed',
                'family_name' => 'Ali',
                'email' => 'user@user.com',
                'phone_number' => '01112345678',
                'address' => 'Giza, Egypt',
                'password' => '12345678',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
