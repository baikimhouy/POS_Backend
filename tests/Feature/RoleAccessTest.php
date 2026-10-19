<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 403 and no data when a cashier hits an admin-only endpoint', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->getJson('/api/v1/users')
        ->assertForbidden()
        ->assertJsonMissingPath('data');
});

it('lets an admin list users', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();

    $this->actingAs($admin)->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lets an admin create a cashier and writes an audit log', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->postJson('/api/v1/users', [
        'name' => 'New Cashier', 'email' => 'new@stylepos.test',
        'password' => 'secret-pass-1', 'role' => 'cashier',
    ])->assertCreated()->assertJsonPath('data.role', 'cashier');

    expect(AuditLog::where('action', 'user.created')->count())->toBe(1);
});

it('stops a cashier from creating users', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->postJson('/api/v1/users', [
        'name' => 'X', 'email' => 'x@stylepos.test', 'password' => 'secret-pass-1', 'role' => 'admin',
    ])->assertForbidden();

    expect(User::where('email', 'x@stylepos.test')->exists())->toBeFalse();
});

it('does not let an admin deactivate themselves', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patchJson("/api/v1/users/{$admin->id}/status", ['is_active' => false])
        ->assertStatus(422);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('lets an admin deactivate a cashier', function () {
    $admin = User::factory()->admin()->create();
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($admin)->patchJson("/api/v1/users/{$cashier->id}/status", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
});
