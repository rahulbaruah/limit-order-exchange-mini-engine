<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app',
    ]);

    // Channels registered at boot belong to the "null" driver, so register them on pusher too.
    require base_path('routes/channels.php');
});

test('a user can authorize their own private channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$user->id}",
            'socket_id' => '1234.5678',
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test("a user cannot authorize another user's private channel", function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', [
            'channel_name' => "private-user.{$otherUser->id}",
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

test('a guest cannot authorize a private channel', function () {
    $user = User::factory()->create();

    $this->post('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
