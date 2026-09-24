<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => fakeSolar());

it('renders auth pages', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
    $this->get('/register')->assertOk()->assertSee('Create account');
});

it('registers a user and signs them in', function () {
    $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

it('signs in and signs out an existing user', function () {
    $user = User::factory()->create(['password' => 'password1234']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password1234',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('requires auth to access alerts index', function () {
    $this->get('/alerts')->assertRedirect('/login');

    $this->actingAs(User::factory()->create())
        ->get('/alerts')
        ->assertOk()
        ->assertSee('Your visibility alerts');
});
