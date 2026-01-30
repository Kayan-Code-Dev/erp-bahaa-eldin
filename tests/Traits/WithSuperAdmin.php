<?php

namespace Tests\Traits;

use App\Models\User;

trait WithSuperAdmin
{
    protected User $superAdmin;

    protected function setupSuperAdmin(): void
    {
        $this->superAdmin = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);
    }

    /**
     * Create a super admin user for testing
     */
    protected function createSuperAdmin(): User
    {
        return User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);
    }

    /**
     * Get authenticated as super admin
     */
    protected function actingAsSuperAdmin(): static
    {
        $user = User::factory()->create([
            'email' => User::SUPER_ADMIN_EMAIL,
        ]);
        return $this->actingAs($user);
    }
}






