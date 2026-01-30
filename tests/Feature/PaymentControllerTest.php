<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Client;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use Laravel\Sanctum\Sanctum;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_payment_with_default_pending_status()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 100.00,
        ]);

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'payment' => [
                    'status' => 'pending', // Default status
                ],
            ]);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'pending',
            'created_by' => $user->id, // Auto-filled from authenticated user
        ]);
    }

    public function test_store_creates_payment_with_paid_status()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 100.00,
        ]);

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'status' => 'paid',
            ]);

        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'paid',
            'created_by' => $user->id,
        ]);
    }

    public function test_store_rejects_canceled_status()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 100.00,
        ]);

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'status' => 'canceled', // Should be rejected
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_auto_fills_created_by_from_authenticated_user()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 100.00,
        ]);

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201);

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertEquals($user->id, $payment->created_by);
    }

    public function test_store_does_not_accept_created_by_in_request()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address = Address::factory()->create(['city_id' => $city->id]);
        $client = Client::factory()->create(['address_id' => $address->id]);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'total_price' => 100.00,
        ]);

        $data = [
            'order_id' => $order->id,
            'amount' => 50.00,
            'created_by' => $otherUser->id, // Should be ignored
        ];

        $response = $this->postJson('/api/v1/payments', $data);

        $response->assertStatus(201);

        $payment = Payment::where('order_id', $order->id)->first();
        // Should use authenticated user, not the one in request
        $this->assertEquals($user->id, $payment->created_by);
        $this->assertNotEquals($otherUser->id, $payment->created_by);
    }

    public function test_update_endpoint_returns_405()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $payment = Payment::factory()->create();

        $response = $this->putJson("/api/v1/payments/{$payment->id}", [
            'amount' => 100.00,
        ]);

        // Since we removed the update route, it should return 405 Method Not Allowed
        $response->assertStatus(405);
    }

    public function test_destroy_endpoint_returns_405()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $payment = Payment::factory()->create();

        $response = $this->deleteJson("/api/v1/payments/{$payment->id}");

        // Since we removed the update route, it should return 405 Method Not Allowed
        $response->assertStatus(405);
    }

    public function test_index_returns_payments()
    {
        Payment::factory()->count(3)->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(200);
    }

    public function test_show_returns_payment()
    {
        $payment = Payment::factory()->create();
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson(['id' => $payment->id]);
    }
}



