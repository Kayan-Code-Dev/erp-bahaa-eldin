<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Cloth;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory as FactoryModel;
use App\Models\ClothType;
use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class ClothControllerFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_by_entity_type_branch_only_returns_branch_clothes()
    {
        DB::enableQueryLog();
        
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        // Create countries, cities, addresses
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address1 = Address::factory()->create(['city_id' => $city->id]);
        $address2 = Address::factory()->create(['city_id' => $city->id]);
        $address3 = Address::factory()->create(['city_id' => $city->id]);

        // Create entities with inventories
        $branch = Branch::factory()->create(['address_id' => $address1->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);

        $workshop = Workshop::factory()->create(['address_id' => $address2->id]);
        $workshop->inventory()->create(['name' => 'Workshop Inventory']);

        $factory = FactoryModel::factory()->create(['address_id' => $address3->id]);
        $factory->inventory()->create(['name' => 'Factory Inventory']);

        // Create cloth type
        $clothType = ClothType::factory()->create();

        // Create clothes and assign to specific inventories
        $branchCloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id, 'code' => 'BR-CL-001']);
        $branch->inventory->clothes()->attach($branchCloth->id);

        $workshopCloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id, 'code' => 'WS-CL-001']);
        $workshop->inventory->clothes()->attach($workshopCloth->id);

        $factoryCloth = Cloth::factory()->create(['cloth_type_id' => $clothType->id, 'code' => 'FA-CL-001']);
        $factory->inventory->clothes()->attach($factoryCloth->id);

        // Verify database state
        $this->assertDatabaseHas('inventories', [
            'id' => $branch->inventory->id,
            'inventoriable_type' => 'branch',
            'inventoriable_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'id' => $workshop->inventory->id,
            'inventoriable_type' => 'workshop',
            'inventoriable_id' => $workshop->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'id' => $factory->inventory->id,
            'inventoriable_type' => 'factory',
            'inventoriable_id' => $factory->id,
        ]);

        // Test filter by branch
        $response = $this->getJson('/api/v1/clothes?entity_type=branch');
        
        $queries = DB::getQueryLog();
        $lastQuery = end($queries);
        
        // Debug: Log the query
        \Log::info('Filter by branch query:', ['query' => $lastQuery['query'], 'bindings' => $lastQuery['bindings'] ?? []]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return branch cloth
        $this->assertCount(1, $data);
        $this->assertEquals($branchCloth->id, $data[0]['id']);
        $this->assertEquals('BR-CL-001', $data[0]['code']);

        // Test filter by workshop
        DB::flushQueryLog();
        $response = $this->getJson('/api/v1/clothes?entity_type=workshop');
        
        $queries = DB::getQueryLog();
        $lastQuery = end($queries);
        \Log::info('Filter by workshop query:', ['query' => $lastQuery['query'], 'bindings' => $lastQuery['bindings'] ?? []]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return workshop cloth
        $this->assertCount(1, $data);
        $this->assertEquals($workshopCloth->id, $data[0]['id']);
        $this->assertEquals('WS-CL-001', $data[0]['code']);

        // Test filter by factory
        DB::flushQueryLog();
        $response = $this->getJson('/api/v1/clothes?entity_type=factory');
        
        $queries = DB::getQueryLog();
        $lastQuery = end($queries);
        \Log::info('Filter by factory query:', ['query' => $lastQuery['query'], 'bindings' => $lastQuery['bindings'] ?? []]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only return factory cloth
        $this->assertCount(1, $data);
        $this->assertEquals($factoryCloth->id, $data[0]['id']);
        $this->assertEquals('FA-CL-001', $data[0]['code']);
    }

    public function test_filter_verifies_inventoriable_type_values()
    {
        $user = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
        Sanctum::actingAs($user);

        // Create entities
        $country = Country::factory()->create();
        $city = City::factory()->create(['country_id' => $country->id]);
        $address1 = Address::factory()->create(['city_id' => $city->id]);
        $address2 = Address::factory()->create(['city_id' => $city->id]);
        $address3 = Address::factory()->create(['city_id' => $city->id]);

        $branch = Branch::factory()->create(['address_id' => $address1->id]);
        $branch->inventory()->create(['name' => 'Branch Inventory']);

        $workshop = Workshop::factory()->create(['address_id' => $address2->id]);
        $workshop->inventory()->create(['name' => 'Workshop Inventory']);

        $factory = FactoryModel::factory()->create(['address_id' => $address3->id]);
        $factory->inventory()->create(['name' => 'Factory Inventory']);

        // Check actual database values
        $branchInventory = DB::table('inventories')->where('id', $branch->inventory->id)->first();
        $workshopInventory = DB::table('inventories')->where('id', $workshop->inventory->id)->first();
        $factoryInventory = DB::table('inventories')->where('id', $factory->inventory->id)->first();

        \Log::info('Branch inventory type:', ['type' => $branchInventory->inventoriable_type]);
        \Log::info('Workshop inventory type:', ['type' => $workshopInventory->inventoriable_type]);
        \Log::info('Factory inventory type:', ['type' => $factoryInventory->inventoriable_type]);

        // Verify they are using short names
        $this->assertEquals('branch', $branchInventory->inventoriable_type);
        $this->assertEquals('workshop', $workshopInventory->inventoriable_type);
        $this->assertEquals('factory', $factoryInventory->inventoriable_type);
    }
}



