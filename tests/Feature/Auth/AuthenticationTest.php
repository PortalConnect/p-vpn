<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginLink;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_magic_link_is_sent_for_existing_user(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post('/auth/magic-link', ['email' => $user->email]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'magic-link-sent');
        Mail::assertQueued(LoginLink::class);
        $this->assertDatabaseHas('login_tokens', ['user_id' => $user->id]);
        $this->assertGuest();
    }

    public function test_magic_link_logs_user_in_once(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        [, $plain] = LoginToken::issue($user);

        $response = $this->get("/auth/magic-link/{$plain}");

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->email_verified_at);

        // Повторное использование той же ссылки — вход не происходит.
        auth()->logout();
        $this->get("/auth/magic-link/{$plain}")->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_expired_magic_link_is_rejected(): void
    {
        $user = User::factory()->create();
        [$token, $plain] = LoginToken::issue($user);
        $token->update(['expires_at' => now()->subMinute()]);

        $this->get("/auth/magic-link/{$plain}")->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
