<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Role;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use App\Models\Inventory;
use App\Models\Client;
use App\Models\Cloth;
use App\Models\Order;
use App\Models\Transfer;
use Illuminate\Support\Str;

class TestAllCrud extends Command
{
    protected $signature = 'test:all-crud {--base-url=http://localhost:8000}';
    protected $description = 'Test all CRUD operations for all controllers with valid data';

    private $baseUrl;
    private $token;
    private $testData = [];
    private $results = [];

    public function handle()
    {
        $this->baseUrl = $this->option('base-url');
        $this->info('Starting CRUD tests for all controllers...');
        $this->newLine();

        // Create test user and authenticate
        if (!$this->authenticate()) {
            $this->error('Failed to authenticate. Make sure you have a user in the database.');
            return 1;
        }

        // Create base test data (countries, cities, etc.)
        $this->createBaseTestData();

        // Test each controller
        $this->testCountries();
        $this->testCities();
        $this->testAddresses();
        $this->testCategories();
        $this->testSubcategories();
        $this->testRoles();
        $this->testUsers();
        $this->testBranches();
        $this->testWorkshops();
        $this->testFactories();
        $this->testInventories();
        $this->testClients();
        $this->testClothes();
        $this->testOrders();
        $this->testTransfers();

        // Print summary
        $this->printSummary();

        return 0;
    }

    private function authenticate()
    {
        $password = 'bahaa-eldin323311';

        // Create or get test user and ensure password is correct
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make($password)
            ]
        );

        // Always update password to ensure it matches
        $user->password = Hash::make($password);
        $user->save();

        // Login
        $response = Http::post("{$this->baseUrl}/api/v1/login", [
            'email' => 'test@example.com',
            'password' => $password
        ]);

        if ($response->successful() && $response->json('token')) {
            $this->token = $response->json('token');
            $this->testData['user_id'] = $user->id;
            return true;
        }

        // Log the error for debugging
        $this->warn('Login failed. Status: ' . $response->status() . ', Response: ' . $response->body());
        return false;
    }

    private function createBaseTestData()
    {
        $this->info('Creating base test data...');

        // Create country
        $country = Country::firstOrCreate(
            ['name' => 'Test Country'],
            ['name' => 'Test Country']
        );
        $this->testData['country_id'] = $country->id;

        // Create city
        $city = City::firstOrCreate(
            ['name' => 'Test City', 'country_id' => $country->id],
            ['name' => 'Test City', 'country_id' => $country->id]
        );
        $this->testData['city_id'] = $city->id;

        // Create address
        $address = Address::firstOrCreate(
            ['street' => 'Test Street', 'building' => '1', 'city_id' => $city->id],
            ['street' => 'Test Street', 'building' => '1', 'city_id' => $city->id]
        );
        $this->testData['address_id'] = $address->id;

        // Create category
        $category = Category::firstOrCreate(
            ['name' => 'Test Category'],
            ['name' => 'Test Category', 'description' => 'Test Description']
        );
        $this->testData['category_id'] = $category->id;

        // Create subcategory
        $subcategory = Subcategory::firstOrCreate(
            ['name' => 'Test Subcategory', 'category_id' => $category->id],
            ['name' => 'Test Subcategory', 'category_id' => $category->id, 'description' => 'Test Description']
        );
        $this->testData['subcategory_id'] = $subcategory->id;

        // Create role
        $role = Role::firstOrCreate(
            ['name' => 'test_role'],
            ['name' => 'test_role', 'description' => 'Test Role Description']
        );
        $this->testData['role_id'] = $role->id;
    }

    private function makeRequest($method, $endpoint, $data = [])
    {
        $response = Http::withToken($this->token)
            ->{strtolower($method)}("{$this->baseUrl}/api/v1/{$endpoint}", $data);

        $json = $response->json();
        $errorMessage = 'Unknown error';

        if (isset($json['errors'])) {
            $errorMessage = json_encode($json['errors']);
        } elseif (isset($json['message'])) {
            $errorMessage = $json['message'];
        } elseif (!$response->successful()) {
            $errorMessage = "HTTP {$response->status()}: " . $response->body();
        }

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $json,
            'errors' => $json['errors'] ?? null,
            'error_message' => $errorMessage
        ];
    }

    private function testCrud($resource, $storeData, $updateData = null)
    {
        $results = [
            'index' => false,
            'store' => false,
            'show' => false,
            'update' => false,
            'delete' => false,
            'created_id' => null
        ];

        $this->info("Testing {$resource}...");

        // Test INDEX
        $result = $this->makeRequest('GET', $resource);
        $results['index'] = $result['success'];
        $this->line("  ✓ INDEX: " . ($result['success'] ? 'PASS' : 'FAIL'));

        // Test STORE
        $result = $this->makeRequest('POST', $resource, $storeData);
        $results['store'] = $result['success'];
        if ($result['success'] && isset($result['data']['id'])) {
            $results['created_id'] = $result['data']['id'];
            $this->line("  ✓ STORE: PASS (ID: {$results['created_id']})");
        } else {
            $this->line("  ✗ STORE: FAIL - " . ($result['error_message'] ?? json_encode($result['errors'] ?? 'Unknown error')));
        }

        // Test SHOW
        if ($results['created_id']) {
            $result = $this->makeRequest('GET', "{$resource}/{$results['created_id']}");
            $results['show'] = $result['success'];
            $this->line("  " . ($result['success'] ? '✓' : '✗') . " SHOW: " . ($result['success'] ? 'PASS' : 'FAIL'));
        }

        // Test UPDATE
        if ($results['created_id'] && $updateData) {
            $result = $this->makeRequest('PUT', "{$resource}/{$results['created_id']}", $updateData);
            $results['update'] = $result['success'];
            $this->line("  " . ($result['success'] ? '✓' : '✗') . " UPDATE: " . ($result['success'] ? 'PASS' : 'FAIL'));
        }

        // Test DELETE
        if ($results['created_id']) {
            $result = $this->makeRequest('DELETE', "{$resource}/{$results['created_id']}");
            $results['delete'] = $result['success'];
            $this->line("  " . ($result['success'] ? '✓' : '✗') . " DELETE: " . ($result['success'] ? 'PASS' : 'FAIL'));
        }

        $this->results[$resource] = $results;
        $this->newLine();
    }

    private function testCountries()
    {
        $this->testCrud('countries', [
            'name' => 'Test Country ' . Str::random(5)
        ], [
            'name' => 'Updated Country ' . Str::random(5)
        ]);
    }

    private function testCities()
    {
        $this->testCrud('cities', [
            'name' => 'Test City ' . Str::random(5),
            'country_id' => $this->testData['country_id']
        ], [
            'name' => 'Updated City ' . Str::random(5),
            'country_id' => $this->testData['country_id']
        ]);
    }

    private function testAddresses()
    {
        $this->testCrud('addresses', [
            'street' => 'Test Street ' . Str::random(5),
            'building' => '1',
            'city_id' => $this->testData['city_id']
        ], [
            'street' => 'Updated Street ' . Str::random(5),
            'building' => '2',
            'city_id' => $this->testData['city_id']
        ]);
    }

    private function testCategories()
    {
        $this->testCrud('categories', [
            'name' => 'Test Category ' . Str::random(5),
            'description' => 'Test Description'
        ], [
            'name' => 'Updated Category ' . Str::random(5),
            'description' => 'Updated Description'
        ]);
    }

    private function testSubcategories()
    {
        $this->testCrud('subcategories', [
            'name' => 'Test Subcategory ' . Str::random(5),
            'category_id' => $this->testData['category_id'],
            'description' => 'Test Description'
        ], [
            'name' => 'Updated Subcategory ' . Str::random(5),
            'category_id' => $this->testData['category_id'],
            'description' => 'Updated Description'
        ]);
    }

    private function testRoles()
    {
        $this->testCrud('roles', [
            'name' => 'test_role_' . Str::random(5),
            'description' => 'Test Role Description ' . Str::random(5)
        ], [
            'name' => 'updated_role_' . Str::random(5),
            'description' => 'Updated Role Description ' . Str::random(5)
        ]);
    }

    private function testUsers()
    {
        $email = 'testuser' . Str::random(5) . '@example.com';
        $this->testCrud('users', [
            'name' => 'Test User ' . Str::random(5),
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ], [
            'name' => 'Updated User ' . Str::random(5),
            'email' => 'updated' . $email
        ]);
    }

    private function testBranches()
    {
        $this->testCrud('branches', [
            'branch_code' => 'BR' . Str::random(5),
            'name' => 'Test Branch ' . Str::random(5),
            'address' => [
                'street' => 'Branch Street ' . Str::random(5),
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ], [
            'branch_code' => 'BR' . Str::random(5),
            'name' => 'Updated Branch ' . Str::random(5),
            'address' => [
                'street' => 'Updated Branch Street ' . Str::random(5),
                'building' => '2',
                'city_id' => $this->testData['city_id']
            ]
        ]);
    }

    private function testWorkshops()
    {
        $this->testCrud('workshops', [
            'workshop_code' => 'WS' . Str::random(5),
            'name' => 'Test Workshop ' . Str::random(5),
            'address' => [
                'street' => 'Workshop Street ' . Str::random(5),
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ], [
            'workshop_code' => 'WS' . Str::random(5),
            'name' => 'Updated Workshop ' . Str::random(5),
            'address' => [
                'street' => 'Updated Workshop Street ' . Str::random(5),
                'building' => '2',
                'city_id' => $this->testData['city_id']
            ]
        ]);
    }

    private function testFactories()
    {
        $this->testCrud('factories', [
            'factory_code' => 'FA' . Str::random(5),
            'name' => 'Test Factory ' . Str::random(5),
            'address' => [
                'street' => 'Factory Street ' . Str::random(5),
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ], [
            'factory_code' => 'FA' . Str::random(5),
            'name' => 'Updated Factory ' . Str::random(5),
            'address' => [
                'street' => 'Updated Factory Street ' . Str::random(5),
                'building' => '2',
                'city_id' => $this->testData['city_id']
            ]
        ]);
    }

    private function testInventories()
    {
        // Note: Branches/Workshops/Factories automatically create inventories when created
        // So we can't create a new inventory for an entity that already has one
        // We'll test with a factory instead (create factory, then try to create inventory - should fail)
        // Then test updating an existing inventory

        // Create a factory (which will auto-create an inventory)
        $factoryResult = $this->makeRequest('POST', 'factories', [
            'factory_code' => 'INV_FA' . Str::random(5),
            'name' => 'Inventory Factory ' . Str::random(5),
            'address' => [
                'street' => 'Inventory Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ]);

        if (!$factoryResult['success'] || !isset($factoryResult['data']['id'])) {
            $this->warn('  ⚠ Could not create factory for inventory test, skipping...');
            return;
        }

        $factoryId = $factoryResult['data']['id'];
        $inventoryId = $factoryResult['data']['inventory']['id'] ?? null;

        // Test INDEX
        $result = $this->makeRequest('GET', 'inventories');
        $indexPass = $result['success'];
        if (!$indexPass) {
            $this->line("  ✗ INDEX: FAIL - " . ($result['error_message'] ?? json_encode($result['errors'] ?? 'Unknown error')));
        } else {
            $this->line("  ✓ INDEX: PASS");
        }

        // Test SHOW (using existing inventory)
        if ($inventoryId) {
            $result = $this->makeRequest('GET', "inventories/{$inventoryId}");
            $showPass = $result['success'];
            $this->line("  " . ($showPass ? '✓' : '✗') . " SHOW: " . ($showPass ? 'PASS' : 'FAIL'));
        } else {
            $showPass = false;
            $this->line("  ✗ SHOW: FAIL - No inventory ID");
        }

        // Test STORE - should fail because factory already has inventory
        $result = $this->makeRequest('POST', 'inventories', [
            'name' => 'Test Inventory ' . Str::random(5),
            'inventoriable_type' => 'factory',
            'inventoriable_id' => $factoryId
        ]);
        // This should fail (entity already has inventory) - that's expected
        $storePass = !$result['success']; // We expect this to fail
        $this->line("  " . ($storePass ? '✓' : '✗') . " STORE: " . ($storePass ? 'PASS (correctly rejected)' : 'FAIL'));

        // Test UPDATE (update existing inventory)
        if ($inventoryId) {
            $result = $this->makeRequest('PUT', "inventories/{$inventoryId}", [
                'name' => 'Updated Inventory ' . Str::random(5)
            ]);
            $updatePass = $result['success'];
            $this->line("  " . ($updatePass ? '✓' : '✗') . " UPDATE: " . ($updatePass ? 'PASS' : 'FAIL'));
        } else {
            $updatePass = false;
            $this->line("  ✗ UPDATE: FAIL - No inventory ID");
        }

        // Test DELETE (delete the inventory, which will also delete the factory's reference)
        if ($inventoryId) {
            $result = $this->makeRequest('DELETE', "inventories/{$inventoryId}");
            $deletePass = $result['success'];
            $this->line("  " . ($deletePass ? '✓' : '✗') . " DELETE: " . ($deletePass ? 'PASS' : 'FAIL'));
        } else {
            $deletePass = false;
            $this->line("  ✗ DELETE: FAIL - No inventory ID");
        }

        $this->results['inventories'] = [
            'index' => $indexPass,
            'store' => $storePass,
            'show' => $showPass,
            'update' => $updatePass,
            'delete' => $deletePass,
            'created_id' => $inventoryId
        ];
        $this->newLine();
    }

    private function testClients()
    {
        $nationalId = str_pad(rand(1, 99999999999999), 14, '0', STR_PAD_LEFT);
        $this->testCrud('clients', [
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'Client ' . Str::random(5),
            'date_of_birth' => '1990-01-01',
            'national_id' => $nationalId,
            'address' => [
                'street' => 'Client Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ],
            'phones' => [
                [
                    'phone' => '0123456789' . rand(100, 999),
                    'type' => 'mobile'
                ]
            ]
        ], [
            'first_name' => 'Updated',
            'middle_name' => 'Updated',
            'last_name' => 'Updated Client ' . Str::random(5)
        ]);
    }

    private function testClothes()
    {
        // Create a branch for cloth entity via API
        $branchResult = $this->makeRequest('POST', 'branches', [
            'branch_code' => 'CLOTH_BR' . Str::random(5),
            'name' => 'Cloth Branch ' . Str::random(5),
            'address' => [
                'street' => 'Cloth Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ]);

        if (!$branchResult['success'] || !isset($branchResult['data']['id'])) {
            $this->warn('  ⚠ Could not create branch for cloth test, skipping...');
            return;
        }

        $branchId = $branchResult['data']['id'];

        $code = 'CL-' . Str::random(5);
        $this->testCrud('clothes', [
            'code' => $code,
            'name' => 'Test Cloth ' . Str::random(5),
            'entity_type' => 'branch',
            'entity_id' => $branchId,
            'subcat_id' => [$this->testData['subcategory_id']]
        ], [
            'name' => 'Updated Cloth ' . Str::random(5)
        ]);
    }

    private function testOrders()
    {
        // Create client via API first
        $nationalId = str_pad(rand(1, 99999999999999), 14, '0', STR_PAD_LEFT);
        $clientResult = $this->makeRequest('POST', 'clients', [
            'first_name' => 'Order',
            'middle_name' => 'Test',
            'last_name' => 'Client ' . Str::random(5),
            'date_of_birth' => '1990-01-01',
            'national_id' => $nationalId,
            'address' => [
                'street' => 'Order Client Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ],
            'phones' => [
                [
                    'phone' => '0123456789' . rand(100, 999),
                    'type' => 'mobile'
                ]
            ]
        ]);

        if (!$clientResult['success'] || !isset($clientResult['data']['id'])) {
            $this->warn('  ⚠ Could not create client for order test, skipping...');
            return;
        }

        $clientId = $clientResult['data']['id'];

        // Create branch and its inventory via API
        $branchResult = $this->makeRequest('POST', 'branches', [
            'branch_code' => 'ORDER_BR' . Str::random(5),
            'name' => 'Order Branch ' . Str::random(5),
            'address' => [
                'street' => 'Order Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ]);

        if (!$branchResult['success']) {
            $this->warn('  ⚠ Could not create branch for order test, skipping...');
            return;
        }

        $branchId = $branchResult['data']['id'] ?? null;
        if (!$branchId) {
            $this->warn('  ⚠ Could not get branch ID for order test, skipping...');
            return;
        }

        // Get the inventory that was automatically created with the branch
        $inventoryId = $branchResult['data']['inventory']['id'] ?? null;
        if (!$inventoryId) {
            $this->warn('  ⚠ Could not get inventory ID from branch for order test, skipping...');
            return;
        }

        // Create a cloth for the order
        $clothResult = $this->makeRequest('POST', 'clothes', [
            'code' => 'ORDER_CL' . Str::random(5),
            'name' => 'Order Cloth ' . Str::random(5),
            'entity_type' => 'branch',
            'entity_id' => $branchId,
            'subcat_id' => [$this->testData['subcategory_id']]
        ]);

        if (!$clothResult['success'] || !isset($clothResult['data']['id'])) {
            $this->warn('  ⚠ Could not create cloth for order test, skipping...');
            return;
        }

        $clothId = $clothResult['data']['id'];
        
        $this->testCrud('orders', [
            'client_id' => $clientId,
            'entity_type' => 'branch',
            'entity_id' => $branchId,
            'status' => 'created',
            'paid' => 50.00,
            'visit_datetime' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'cloth_id' => $clothId,
                    'price' => 50.00,
                    'type' => 'rent',
                    'days_of_rent' => 3,
                    'occasion_datetime' => now()->addDays(7)->format('Y-m-d H:i:s'),
                    'status' => 'created'
                ]
            ]
        ], [
            'status' => 'partially_paid',
            'paid' => 75.00
        ]);
    }

    private function testTransfers()
    {
        // Create source and destination branches via API
        $fromBranchResult = $this->makeRequest('POST', 'branches', [
            'branch_code' => 'FROM_BR' . Str::random(5),
            'name' => 'From Branch ' . Str::random(5),
            'address' => [
                'street' => 'From Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ]);

        $toBranchResult = $this->makeRequest('POST', 'branches', [
            'branch_code' => 'TO_BR' . Str::random(5),
            'name' => 'To Branch ' . Str::random(5),
            'address' => [
                'street' => 'To Street',
                'building' => '1',
                'city_id' => $this->testData['city_id']
            ]
        ]);

        if (!$fromBranchResult['success'] || !isset($fromBranchResult['data']['id'])) {
            $this->warn('  ⚠ Could not create from branch for transfer test, skipping...');
            return;
        }

        if (!$toBranchResult['success'] || !isset($toBranchResult['data']['id'])) {
            $this->warn('  ⚠ Could not create to branch for transfer test, skipping...');
            return;
        }

        $fromBranchId = $fromBranchResult['data']['id'];
        $toBranchId = $toBranchResult['data']['id'];

        // Create cloth for transfer via API
        $clothResult = $this->makeRequest('POST', 'clothes', [
            'code' => 'TRANSFER_CL' . Str::random(5),
            'name' => 'Transfer Cloth ' . Str::random(5),
            'entity_type' => 'branch',
            'entity_id' => $fromBranchId,
            'subcat_id' => [$this->testData['subcategory_id']]
        ]);

        if (!$clothResult['success'] || !isset($clothResult['data']['id'])) {
            $this->warn('  ⚠ Could not create cloth for transfer test, skipping...');
            return;
        }

        $clothId = $clothResult['data']['id'];

        // Verify cloth exists and is in inventory
        $cloth = Cloth::find($clothId);
        if (!$cloth) {
            $this->warn('  ⚠ Cloth not found after creation, skipping transfer test...');
            return;
        }

        // Ensure cloth is in the source branch's inventory
        $fromBranch = Branch::find($fromBranchId);
        if ($fromBranch && $fromBranch->inventory) {
            // Check if cloth is already in inventory
            $clothInInventory = $fromBranch->inventory->clothes()->where('clothes.id', $clothId)->first();
            if (!$clothInInventory) {
                // Add cloth to inventory if not already there
                $fromBranch->inventory->clothes()->attach($clothId);
            }
        }

        $this->testCrud('transfers', [
            'from_entity_type' => 'branch',
            'from_entity_id' => $fromBranchId,
            'to_entity_type' => 'branch',
            'to_entity_id' => $toBranchId,
            'transfer_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'cloth_id' => $clothId
                ]
            ]
        ], [
            'transfer_date' => now()->addDay()->format('Y-m-d'),
            'notes' => 'Updated transfer notes'
        ]);
    }

    private function printSummary()
    {
        $this->newLine();
        $this->info('=== TEST SUMMARY ===');
        $this->newLine();

        $totalTests = 0;
        $passedTests = 0;

        foreach ($this->results as $resource => $results) {
            $resourceTests = array_filter([
                $results['index'] ? 1 : 0,
                $results['store'] ? 1 : 0,
                $results['show'] ? 1 : 0,
                $results['update'] ? 1 : 0,
                $results['delete'] ? 1 : 0
            ]);

            $resourcePassed = count($resourceTests);
            $totalTests += 5;
            $passedTests += $resourcePassed;

            $status = $resourcePassed === 5 ? '✓' : '✗';
            $this->line("{$status} {$resource}: {$resourcePassed}/5 tests passed");
        }

        $this->newLine();
        $this->info("Total: {$passedTests}/{$totalTests} tests passed");

        if ($passedTests === $totalTests) {
            $this->info('All tests passed! ✓');
        } else {
            $this->warn('Some tests failed. Please check the output above.');
        }
    }
}

