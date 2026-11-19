<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Never ship a known admin password. The email comes from
        // ADMIN_EMAIL (default admin@dabberhaly.com) and the password from
        // ADMIN_PASSWORD. If no password is configured, a random one is
        // generated and printed ONCE to the console — it is not stored
        // anywhere else, so save it when you see it.
        $email = env('ADMIN_EMAIL', 'admin@dabberhaly.com');
        $configuredPassword = env('ADMIN_PASSWORD');

        $existing = Admin::where('email', $email)->first();

        if ($existing && ! $configuredPassword) {
            // Already seeded: do not reset the password on every re-seed.
            return;
        }

        $password = $configuredPassword ?: Str::random(16);

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
            $this->command->warn('Admin account created — save these credentials, they will not be shown again:');
            $this->command->info("  Email:    {$email}");
            $this->command->info("  Password: {$password}");
        }
    }
}
