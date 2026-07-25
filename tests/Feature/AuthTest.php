<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('login, mengambil profil, dan logout dengan kontrak yang konsisten', function () {
    $user = User::factory()->create([
        'username' => 'admin',
        'password' => Hash::make('rahasia'),
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'username' => 'admin',
        'password' => 'rahasia',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user']]);

    $token = $login->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('menolak password login yang salah', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => Hash::make('rahasia'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'username' => 'admin',
        'password' => 'salah',
    ])->assertUnauthorized();
});
