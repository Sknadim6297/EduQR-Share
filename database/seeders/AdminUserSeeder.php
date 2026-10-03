<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('default_admin.email');
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $this->command?->warn("Account {$email} already exists; its password was not changed.");

            return;
        }

        $password = config('default_admin.password');
        $generatedPassword = ! is_string($password) || trim($password) === '';

        if ($generatedPassword) {
            $password = Str::random(40);
        }

        User::query()->create([
            'name' => config('default_admin.name'),
            'email' => $email,
            'password' => $password,
        ]);

        $this->command?->info("Admin sign-in email: {$email}");

        if ($generatedPassword) {
            $this->command?->warn("One-time generated password: {$password}");
        } else {
            $this->command?->info('The configured DEFAULT_ADMIN_PASSWORD was used.');
        }
    }
}
