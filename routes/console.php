<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schoolqr:create-teacher', function () {
    $name = $this->ask('Teacher name');
    $email = $this->ask('School email');
    $password = $this->secret('Password');
    $passwordConfirmation = $this->secret('Confirm password');

    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $passwordConfirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:12', 'confirmed'],
    ]);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => $password,
    ]);

    $this->info('Teacher account created.');

    return 0;
})->purpose('Create a teacher account without enabling public registration');
