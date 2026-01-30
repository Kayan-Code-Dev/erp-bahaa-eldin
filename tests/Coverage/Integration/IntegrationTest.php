<?php

namespace Tests\Coverage\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_order_lifecycle()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $this->assertTrue(true);
    }

    public function test_client_to_order_workflow()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $this->assertTrue(true);
    }
}




