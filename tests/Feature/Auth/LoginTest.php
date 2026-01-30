<?php

namespace Tests\Feature\Auth;

use Tests\Feature\BaseTestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication Login Tests
 *
 * Tests login functionality according to TEST_COVERAGE.md specification
 */
class LoginTest extends BaseTestCase
{
    /**
     * Test: Login with Valid Credentials
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Required Permission: None (public endpoint)
     * - Expected Status: 200
     * - Description: User can login with valid email and password
     * - Should Pass For: All users (authenticated or not)
     * - Should Fail For: None
     */
    public function test_auth_login_with_valid_credentials_succeeds()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $responseData = $response->json();
        $this->assertNotEmpty($responseData['token']);
        $this->assertEquals($user->id, $responseData['user']['id']);
        $this->assertEquals($user->email, $responseData['user']['email']);
    }

    /**
     * Test: Login with Invalid Credentials
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Required Permission: None
     * - Expected Status: 401
     * - Description: Login fails with invalid credentials
     * - Should Pass For: All users (test validates failure)
     * - Should Fail For: None
     */
    public function test_auth_login_with_invalid_credentials_fails_401()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Test: Login with Non-existent User
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Required Permission: None
     * - Expected Status: 401
     * - Description: Login fails with non-existent email
     */
    public function test_auth_login_with_non_existent_user_fails_401()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Test: Login with Missing Email
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Expected Status: 422
     * - Description: Login requires email field
     */
    public function test_auth_login_missing_email_fails_422()
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test: Login with Missing Password
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Expected Status: 422
     * - Description: Login requires password field
     */
    public function test_auth_login_missing_password_fails_422()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
        ]);

        $this->assertValidationError($response, ['password']);
    }

    /**
     * Test: Login with Invalid Email Format
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Expected Status: 422
     * - Description: Email must be valid format
     */
    public function test_auth_login_invalid_email_format_fails_422()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email-format',
            'password' => 'password123',
        ]);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test: Login with Empty Credentials
     * - Type: Feature Test
     * - Module: Authentication
     * - Endpoint: POST /api/v1/login
     * - Expected Status: 422
     * - Description: Login requires both email and password
     */
    public function test_auth_login_empty_credentials_fails_422()
    {
        $response = $this->postJson('/api/v1/login', []);

        $this->assertValidationError($response, ['email', 'password']);
    }

    /**
     * Test: Login Returns User Data
     * - Type: Feature Test
     * - Module: Authentication
     * - Description: Login response includes complete user information
     */
    public function test_auth_login_returns_complete_user_data()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'name' => 'Test User',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]
            ]);
    }

    /**
     * Test: Login Token Can Be Used for Authenticated Requests
     * - Type: Feature Test
     * - Module: Authentication
     * - Description: Returned token can be used for subsequent authenticated requests
     */
    public function test_auth_login_token_can_be_used_for_authenticated_requests()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Login and get token
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json()['token'];

        // Use token for authenticated request
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/clients');

        // Should not get 401 (should get 403 if no permission, or 200 if has permission)
        $response->assertStatus(403); // Assuming no client permissions
    }
}
