<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration
     */
    public function test_user_registration(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'email' => 'marko@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'klijent',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'marko@example.com',
            'role' => 'klijent',
        ]);
    }

    /**
     * Test registration validation - missing email
     */
    public function test_registration_validation_missing_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'klijent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    /**
     * Test registration validation - invalid email
     */
    public function test_registration_validation_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'klijent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    /**
     * Test registration validation - password too short
     */
    public function test_registration_validation_password_too_short(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'email' => 'marko@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'klijent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    /**
     * Test registration validation - password mismatch
     */
    public function test_registration_validation_password_mismatch(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'email' => 'marko@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'role' => 'klijent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    /**
     * Test registration validation - duplicate email
     */
    public function test_registration_validation_duplicate_email(): void
    {
        User::factory()->create(['email' => 'marko@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marko Marković',
            'email' => 'marko@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'klijent',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    /**
     * Test user login
     */
    public function test_user_login(): void
    {
        $user = User::factory()->create([
            'email' => 'marko@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'marko@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'marko@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'marko@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test login with non-existent user
     */
    public function test_login_with_non_existent_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test user logout
     */
    public function test_user_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
    }

    /**
     * Test getting authenticated user
     */
    public function test_get_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $this->assertEquals($user->id, $response->json('data.id'));
    }

    /**
     * Test unauthenticated user cannot access protected route
     */
    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test password reset request
     */
    public function test_password_reset_request(): void
    {
        $user = User::factory()->create(['email' => 'marko@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'marko@example.com',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test password reset with invalid email
     */
    public function test_password_reset_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test user profile update
     */
    public function test_user_profile_update(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/auth/profile', [
            'name' => 'Updated Name',
            'phone' => '061234567',
            'city' => 'Sarajevo',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test changing password
     */
    public function test_change_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test changing password with wrong current password
     */
    public function test_change_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($user)->putJson('/api/v1/user/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test salon registration
     */
    public function test_salon_registration(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Salon Ljepote',
            'email' => 'salon@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salon',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'salon@example.com',
            'role' => 'salon',
        ]);
    }

    /**
     * Test admin registration (should fail)
     */
    public function test_admin_registration_should_fail(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }
}
