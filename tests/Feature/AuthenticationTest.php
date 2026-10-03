<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_sign_in_and_sign_out(): void
    {
        $teacher = User::factory()->create(['password' => 'school-secret-password']);

        $this->post(route('login.store'), [
            'email' => $teacher->email,
            'password' => 'school-secret-password',
        ])->assertRedirect(route('documents.index'));

        $this->assertAuthenticatedAs($teacher);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_invalid_teacher_credentials_are_rejected(): void
    {
        $teacher = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $teacher->email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_teacher_creation_command_prompts_for_a_confirmed_private_password(): void
    {
        $this->artisan('schoolqr:create-teacher')
            ->expectsQuestion('Teacher name', 'Avery Teacher')
            ->expectsQuestion('School email', 'avery@example.test')
            ->expectsQuestion('Password', 'a-long-private-password')
            ->expectsQuestion('Confirm password', 'a-long-private-password')
            ->expectsOutput('Teacher account created.')
            ->assertExitCode(0);

        $teacher = User::query()->where('email', 'avery@example.test')->firstOrFail();
        $this->assertTrue(password_verify('a-long-private-password', $teacher->password));
    }
}
