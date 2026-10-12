<?php

namespace Tests\Feature;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_is_valid_for_one_day_then_expires(): void
    {
        $designation = Designation::firstOrCreate(
            ['name' => 'Sales Executive'],
            ['status' => 'active', 'can_login' => true, 'api_only' => true],
        );
        $user = User::factory()->create(['designation_id' => $designation->id, 'role' => 'user', 'status' => 'active']);

        $this->travelTo(now()->startOfMinute());
        $login = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('expires_in', 86400)
            ->assertJsonPath('expires_at', now()->addDay()->toIso8601String());
        $token = $login->json('token');

        $this->travel(23)->hours();
        $this->withToken($token)->postJson('/api/dashboard')->assertOk();

        $this->travel(2)->hours();
        $this->withToken($token)->postJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token expired. Please log in again.');

        // Logging in again issues a fresh one-day token.
        $newToken = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->json('token');
        $this->withToken($newToken)->postJson('/api/dashboard')->assertOk();
    }
}
