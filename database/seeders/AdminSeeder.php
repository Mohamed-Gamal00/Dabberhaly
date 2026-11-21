<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // The email comes from ADMIN_EMAIL (default admin@dabberhaly.com)
        // and the password from ADMIN_PASSWORD. If no password is
        // configured, the default password is 12345678 — change it from
        // the admin panel after the first login.
        $email = env('ADMIN_EMAIL', 'admin@dabberhaly.com');
        $configuredPassword = env('ADMIN_PASSWORD');

        $existing = Admin::where('email', $email)->first();

        if ($existing && ! $configuredPassword) {
            // Already seeded: do not reset the password on every re-seed.
            return;
        }

        $password = $configuredPassword ?: '12345678';

        $admin = Admin::updateOrCreate(
            ['email' => $email],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make($password),
            ]
        );

        // super_admin is not mass-assignable; set it directly.
        $admin->super_admin = true;
        $admin->save();

        if (! $configuredPassword && $this->command) {
            $this->command->warn('Admin account created with the default password — change it after the first login:');
            $this->command->info("  Email:    {$email}");
            $this->command->info("  Password: {$password}");
        }
    }
}
