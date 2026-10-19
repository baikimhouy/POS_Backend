<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Sanctum treats requests from a configured SPA origin as stateful (session + cookies).
function spa(): array
{
    return ['Referer' => 'http://localhost:3000', 'Origin' => 'http://localhost:3000'];
}

it('logs in an active user', function () {
    $user = User::factory()->admin()->create(['email' => 'a@stylepos.test']);

    $this->withHeaders(spa())
        ->postJson('/api/v1/login', ['email' => 'a@stylepos.test', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonMissingPath('data.password');
});

it('rejects a wrong password', function () {
    User::factory()->create(['email' => 'a@stylepos.test']);

    $this->withHeaders(spa())
        ->postJson('/api/v1/login', ['email' => 'a@stylepos.test', 'password' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('does not let a deactivated user sign in', function () {
    User::factory()->inactive()->create(['email' => 'off@stylepos.test']);

    $this->withHeaders(spa())
        ->postJson('/api/v1/login', ['email' => 'off@stylepos.test', 'password' => 'password'])
        ->assertStatus(422);
});

it('blocks a deactivated user who already has a session', function () {
    $user = User::factory()->inactive()->create();

    $this->actingAs($user)->getJson('/api/v1/user')->assertForbidden();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
