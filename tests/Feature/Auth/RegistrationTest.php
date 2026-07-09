<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_redirects_to_unified_login(): void
    {
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_new_email_gets_account_and_login_link(): void
    {
        Mail::fake();

        $response = $this->post('/auth/magic-link', ['email' => 'newcomer@example.com']);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newcomer@example.com']);
        Mail::assertQueued(LoginLink::class);

        // Аккаунт создан, но вход только по ссылке из письма.
        $this->assertGuest();
        $this->assertNull(User::where('email', 'newcomer@example.com')->first()->email_verified_at);
    }
}
