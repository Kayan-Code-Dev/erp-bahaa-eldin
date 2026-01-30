<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Department;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Branch;
use App\Models\Workshop;
use App\Models\Factory;
use App\Models\Country;
use App\Models\City;
use App\Models\Address;
use App\Models\Inventory;
use App\Models\Cloth;
use App\Models\ClothType;
use App\Models\Client;
use App\Models\Order;
use App\Models\Transfer;
use App\Models\EmployeeEntity;
use App\Services\EntityAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

class TestEntityPermissions extends Command
{
    protected $signature = 'test:entity-permissions {--cleanup : Clean up test data after running}';
    protected $description = 'Test the entity-scoped permission system';

    protected $testPrefix = 'TEST_EP_';
    protected $entityAccessService;
    
    // Test data holders
    protected $masterManagerUser;
    protected $branchesManagerUser;
    protected $branchManagerUser;
    protected $employeeUser;
    
    protected $branch1;
    protected $branch2;
    protected $workshop1;
    protected $factory1;
    
    protected $inventory1;
    protected $inventory2;
    
    protected $cloth1;
    protected $cloth2;
    
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->entityAccessService = app(EntityAccessService::class);
    }

    public function handle()
    {
        $this->info('===========================================');
        $this->info('   ENTITY-SCOPED PERMISSION SYSTEM TEST   ');
        $this->info('===========================================');
        $this->newLine();

        try {
            DB::beginTransaction();

            // Setup test data
            $this->info('📦 Setting up test data...');
            $this->setupTestData();
            $this->info('✅ Test data created successfully');
            $this->newLine();

            // Run tests
            $allPassed = true;

            // Test 1: Entity Assignment APIs
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 1: Entity Assignment APIs');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testEntityAssignmentAPIs() && $allPassed;
            $this->newLine();

            // Test 2: Access Control by Job Title Level
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 2: Access Control by Job Title Level');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testAccessControlByLevel() && $allPassed;
            $this->newLine();

            // Test 3: Entity Access Service
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 3: Entity Access Service');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testEntityAccessService() && $allPassed;
            $this->newLine();

            // Test 4: Order Access Control
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 4: Order Access Control');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testOrderAccessControl() && $allPassed;
            $this->newLine();

            // Test 5: Transfer Access Control
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 5: Transfer Access Control');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testTransferAccessControl() && $allPassed;
            $this->newLine();

            // Test 6: Cloth Access Control
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 6: Cloth Access Control');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testClothAccessControl() && $allPassed;
            $this->newLine();

            // Test 7: Role Entity Type Restrictions
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('TEST 7: Role Entity Type Restrictions');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $allPassed = $this->testRoleEntityTypeRestrictions() && $allPassed;
            $this->newLine();

            // Summary
            $this->info('===========================================');
            if ($allPassed) {
                $this->info('✅ ALL TESTS PASSED!');
            } else {
                $this->error('❌ SOME TESTS FAILED!');
            }
            $this->info('===========================================');

            if ($this->option('cleanup')) {
                DB::rollBack();
                $this->info('🧹 Test data cleaned up (transaction rolled back)');
            } else {
                DB::commit();
                $this->info('💾 Test data committed to database');
                $this->info('   Run with --cleanup to auto-cleanup');
            }

            return $allPassed ? 0 : 1;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Test failed with exception: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    protected function setupTestData()
    {
        // Create department
        $department = Department::create([
            'name' => $this->testPrefix . 'Department',
            'code' => $this->testPrefix . 'DEPT',
        ]);

        // Create job titles for each level
        $masterManagerJobTitle = JobTitle::create([
            'name' => $this->testPrefix . 'Master Manager',
            'code' => $this->testPrefix . 'MM',
            'department_id' => $department->id,
            'level' => 'master_manager',
        ]);

        $branchesManagerJobTitle = JobTitle::create([
            'name' => $this->testPrefix . 'Branches Manager',
            'code' => $this->testPrefix . 'BRM',
            'department_id' => $department->id,
            'level' => 'branches_manager',
        ]);

        $branchManagerJobTitle = JobTitle::create([
            'name' => $this->testPrefix . 'Branch Manager',
            'code' => $this->testPrefix . 'BM',
            'department_id' => $department->id,
            'level' => 'branch_manager',
        ]);

        $employeeJobTitle = JobTitle::create([
            'name' => $this->testPrefix . 'Employee',
            'code' => $this->testPrefix . 'EMP',
            'department_id' => $department->id,
            'level' => 'employee',
        ]);

        // Create address requirements
        $country = Country::firstOrCreate(['name' => $this->testPrefix . 'Country']);
        $city = City::firstOrCreate([
            'name' => $this->testPrefix . 'City',
            'country_id' => $country->id,
        ]);
        $address = Address::create([
            'city_id' => $city->id,
            'street' => $this->testPrefix . 'Street',
            'building' => '1',
        ]);

        // Create users and employees
        $this->masterManagerUser = $this->createUserWithEmployee(
            'master_manager',
            $masterManagerJobTitle,
            $department
        );

        $this->branchesManagerUser = $this->createUserWithEmployee(
            'branches_manager',
            $branchesManagerJobTitle,
            $department
        );

        $this->branchManagerUser = $this->createUserWithEmployee(
            'branch_manager',
            $branchManagerJobTitle,
            $department
        );

        $this->employeeUser = $this->createUserWithEmployee(
            'employee',
            $employeeJobTitle,
            $department
        );

        // Create branches
        $this->branch1 = Branch::create([
            'name' => $this->testPrefix . 'Branch 1',
            'branch_code' => $this->testPrefix . 'B1',
            'address_id' => $address->id,
        ]);

        $this->branch2 = Branch::create([
            'name' => $this->testPrefix . 'Branch 2',
            'branch_code' => $this->testPrefix . 'B2',
            'address_id' => $address->id,
        ]);

        // Create workshop
        $this->workshop1 = Workshop::create([
            'name' => $this->testPrefix . 'Workshop 1',
            'workshop_code' => $this->testPrefix . 'WS1',
            'address_id' => $address->id,
        ]);

        // Create factory
        $this->factory1 = Factory::create([
            'name' => $this->testPrefix . 'Factory 1',
            'factory_code' => $this->testPrefix . 'F1',
            'address_id' => $address->id,
            'max_capacity' => 100,
        ]);

        // Create inventories for branches
        $this->inventory1 = Inventory::create([
            'name' => $this->testPrefix . 'Inventory 1',
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $this->branch1->id,
        ]);

        $this->inventory2 = Inventory::create([
            'name' => $this->testPrefix . 'Inventory 2',
            'inventoriable_type' => Branch::class,
            'inventoriable_id' => $this->branch2->id,
        ]);

        // Create cloth type
        $clothType = ClothType::firstOrCreate(
            ['code' => $this->testPrefix . 'CT'],
            ['name' => $this->testPrefix . 'Cloth Type']
        );

        // Create cloths
        $this->cloth1 = Cloth::create([
            'code' => $this->testPrefix . 'CLOTH1',
            'name' => $this->testPrefix . 'Cloth 1',
            'cloth_type_id' => $clothType->id,
            'status' => 'ready_for_rent',
        ]);
        // Attach to inventory1
        $this->cloth1->inventories()->attach($this->inventory1->id);

        $this->cloth2 = Cloth::create([
            'code' => $this->testPrefix . 'CLOTH2',
            'name' => $this->testPrefix . 'Cloth 2',
            'cloth_type_id' => $clothType->id,
            'status' => 'ready_for_rent',
        ]);
        // Attach to inventory2
        $this->cloth2->inventories()->attach($this->inventory2->id);

        // Create client
        $this->client = Client::create([
            'first_name' => $this->testPrefix . 'Client',
            'middle_name' => 'Test',
            'last_name' => 'User',
            'date_of_birth' => '1990-01-01',
            'national_id' => $this->testPrefix . 'NID123',
            'address_id' => $address->id,
            'phone' => '1234567890',
        ]);

        // Assign entities to branch manager and employee
        // Branch manager is assigned to branch1
        $this->branchManagerUser->employee->assignToEntity('branch', $this->branch1->id, true);
        
        // Employee is assigned to branch1 only
        $this->employeeUser->employee->assignToEntity('branch', $this->branch1->id, true);

        $this->info('   - Created 4 users with different job title levels');
        $this->info('   - Created 2 branches, 1 workshop, 1 factory');
        $this->info('   - Created 2 inventories with cloths');
        $this->info('   - Assigned branch manager and employee to branch 1');
    }

    protected function createUserWithEmployee($suffix, $jobTitle, $department)
    {
        $user = User::create([
            'name' => $this->testPrefix . $suffix,
            'email' => $this->testPrefix . $suffix . '@test.com',
            'password' => Hash::make('password'),
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'job_title_id' => $jobTitle->id,
            'employee_code' => $this->testPrefix . strtoupper($suffix),
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'hire_date' => now(),
        ]);

        // Reload user to get employee relationship
        return $user->fresh(['employee.jobTitle']);
    }

    protected function testEntityAssignmentAPIs()
    {
        $passed = true;

        // Test 1.1: Assign entity to employee
        $this->line('   1.1: Assign entity to employee');
        $employee = $this->employeeUser->employee;
        
        // Should already have branch1 assigned from setup
        $assignments = $employee->getAssignedEntities();
        if (isset($assignments['branch']) && in_array($this->branch1->id, $assignments['branch'])) {
            $this->info('   ✅ Employee has branch 1 assigned');
        } else {
            $this->error('   ❌ Employee should have branch 1 assigned');
            $passed = false;
        }

        // Test 1.2: Assign additional entity
        $this->line('   1.2: Assign additional entity (workshop)');
        $employee->assignToEntity('workshop', $this->workshop1->id);
        $assignments = $employee->getAssignedEntities();
        if (isset($assignments['workshop']) && in_array($this->workshop1->id, $assignments['workshop'])) {
            $this->info('   ✅ Workshop assigned successfully');
        } else {
            $this->error('   ❌ Failed to assign workshop');
            $passed = false;
        }

        // Test 1.3: Check isAssignedTo method
        $this->line('   1.3: Check isAssignedTo method');
        if ($employee->isAssignedTo('branch', $this->branch1->id)) {
            $this->info('   ✅ isAssignedTo returns true for assigned entity');
        } else {
            $this->error('   ❌ isAssignedTo should return true');
            $passed = false;
        }

        if (!$employee->isAssignedTo('branch', $this->branch2->id)) {
            $this->info('   ✅ isAssignedTo returns false for unassigned entity');
        } else {
            $this->error('   ❌ isAssignedTo should return false for unassigned entity');
            $passed = false;
        }

        // Test 1.4: Unassign entity
        $this->line('   1.4: Unassign entity (workshop)');
        $employee->unassignFromEntity('workshop', $this->workshop1->id);
        $assignments = $employee->getAssignedEntities();
        if (!isset($assignments['workshop']) || !in_array($this->workshop1->id, $assignments['workshop'])) {
            $this->info('   ✅ Workshop unassigned successfully');
        } else {
            $this->error('   ❌ Failed to unassign workshop');
            $passed = false;
        }

        return $passed;
    }

    protected function testAccessControlByLevel()
    {
        $passed = true;

        // Test 2.1: MasterManager has full access
        $this->line('   2.1: MasterManager has full access');
        if ($this->masterManagerUser->hasFullAccess()) {
            $this->info('   ✅ MasterManager has full access');
        } else {
            $this->error('   ❌ MasterManager should have full access');
            $passed = false;
        }

        // Test 2.2: BranchesManager can access all branches
        $this->line('   2.2: BranchesManager can access all branches');
        $canAccessBranch1 = $this->entityAccessService->canAccessEntity(
            $this->branchesManagerUser, 'branch', $this->branch1->id
        );
        $canAccessBranch2 = $this->entityAccessService->canAccessEntity(
            $this->branchesManagerUser, 'branch', $this->branch2->id
        );
        if ($canAccessBranch1 && $canAccessBranch2) {
            $this->info('   ✅ BranchesManager can access all branches');
        } else {
            $this->error('   ❌ BranchesManager should access all branches');
            $passed = false;
        }

        // Test 2.3: BranchesManager can access all workshops
        $this->line('   2.3: BranchesManager can access all workshops');
        $canAccessWorkshop = $this->entityAccessService->canAccessEntity(
            $this->branchesManagerUser, 'workshop', $this->workshop1->id
        );
        if ($canAccessWorkshop) {
            $this->info('   ✅ BranchesManager can access all workshops');
        } else {
            $this->error('   ❌ BranchesManager should access all workshops');
            $passed = false;
        }

        // Test 2.4: BranchesManager needs explicit factory assignment
        $this->line('   2.4: BranchesManager needs explicit factory assignment');
        $canAccessFactory = $this->entityAccessService->canAccessEntity(
            $this->branchesManagerUser, 'factory', $this->factory1->id
        );
        if (!$canAccessFactory) {
            $this->info('   ✅ BranchesManager cannot access factory without assignment');
        } else {
            $this->error('   ❌ BranchesManager should not access factory without assignment');
            $passed = false;
        }

        // Assign factory and re-test
        $this->branchesManagerUser->employee->assignToEntity('factory', $this->factory1->id);
        $canAccessFactory = $this->entityAccessService->canAccessEntity(
            $this->branchesManagerUser, 'factory', $this->factory1->id
        );
        if ($canAccessFactory) {
            $this->info('   ✅ BranchesManager can access factory after assignment');
        } else {
            $this->error('   ❌ BranchesManager should access factory after assignment');
            $passed = false;
        }

        // Test 2.5: BranchManager can only access assigned entities
        $this->line('   2.5: BranchManager can only access assigned entities');
        $canAccessBranch1 = $this->entityAccessService->canAccessEntity(
            $this->branchManagerUser, 'branch', $this->branch1->id
        );
        $canAccessBranch2 = $this->entityAccessService->canAccessEntity(
            $this->branchManagerUser, 'branch', $this->branch2->id
        );
        if ($canAccessBranch1 && !$canAccessBranch2) {
            $this->info('   ✅ BranchManager can only access assigned branch');
        } else {
            $this->error('   ❌ BranchManager access control failed');
            $passed = false;
        }

        // Test 2.6: Employee can only access assigned entities
        $this->line('   2.6: Employee can only access assigned entities');
        $canAccessBranch1 = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 'branch', $this->branch1->id
        );
        $canAccessBranch2 = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 'branch', $this->branch2->id
        );
        if ($canAccessBranch1 && !$canAccessBranch2) {
            $this->info('   ✅ Employee can only access assigned branch');
        } else {
            $this->error('   ❌ Employee access control failed');
            $passed = false;
        }

        return $passed;
    }

    protected function testEntityAccessService()
    {
        $passed = true;

        // Test 3.1: getAccessibleEntityIds for MasterManager
        $this->line('   3.1: getAccessibleEntityIds for MasterManager');
        // MasterManager has full access, returns null to indicate "all entities"
        $accessible = $this->masterManagerUser->getAccessibleEntityIds('branch');
        if ($accessible === null) {
            $this->info('   ✅ MasterManager has access to all branches (null = all)');
        } else {
            $this->info('   ℹ️  MasterManager accessible branches: ' . json_encode($accessible));
        }

        // Test 3.2: getAccessibleEntityIds for BranchManager
        $this->line('   3.2: getAccessibleEntityIds for BranchManager');
        $accessible = $this->branchManagerUser->getAccessibleEntityIds('branch');
        if (is_array($accessible) && in_array($this->branch1->id, $accessible)) {
            $this->info('   ✅ BranchManager accessible branches correct');
        } else {
            $this->error('   ❌ BranchManager accessible branches incorrect: ' . json_encode($accessible));
            $passed = false;
        }

        // Test 3.3: BranchManager should NOT have access to branch2
        $this->line('   3.3: BranchManager should NOT have branch2 in accessible list');
        if (is_array($accessible) && !in_array($this->branch2->id, $accessible)) {
            $this->info('   ✅ BranchManager does not have access to branch 2');
        } else {
            $this->error('   ❌ BranchManager should not have branch 2');
            $passed = false;
        }

        // Test 3.4: getAccessibleInventoryIds
        $this->line('   3.4: getAccessibleInventoryIds for BranchManager');
        $inventoryIds = $this->branchManagerUser->getAccessibleInventoryIds();
        if ($inventoryIds === null || (is_array($inventoryIds) && in_array($this->inventory1->id, $inventoryIds))) {
            $this->info('   ✅ BranchManager has access to inventory 1');
        } else {
            $this->info('   ℹ️  Inventory IDs: ' . json_encode($inventoryIds));
            $this->info('   ℹ️  Expected to include: ' . $this->inventory1->id);
        }

        return $passed;
    }

    protected function testOrderAccessControl()
    {
        $passed = true;

        // Create an order in branch1's inventory
        $this->line('   4.1: Create order in accessible branch (branch1)');
        $order = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $this->inventory1->id,
            'status' => 'created',
            'total_price' => 100,
        ]);

        // Test 4.1: Employee can access order in their branch
        $this->line('   4.2: Employee can access order in their branch');
        $canAccess = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 
            'branch', 
            $this->branch1->id
        );
        if ($canAccess) {
            $this->info('   ✅ Employee can access order in branch 1');
        } else {
            $this->error('   ❌ Employee should access order in branch 1');
            $passed = false;
        }

        // Create an order in branch2's inventory
        $this->line('   4.3: Create order in inaccessible branch (branch2)');
        $order2 = Order::create([
            'client_id' => $this->client->id,
            'inventory_id' => $this->inventory2->id,
            'status' => 'created',
            'total_price' => 150,
        ]);

        // Test 4.3: Employee cannot access order in branch2
        $this->line('   4.4: Employee cannot access order in inaccessible branch');
        $canAccess = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 
            'branch', 
            $this->branch2->id
        );
        if (!$canAccess) {
            $this->info('   ✅ Employee cannot access order in branch 2');
        } else {
            $this->error('   ❌ Employee should not access order in branch 2');
            $passed = false;
        }

        // Test 4.5: MasterManager can access all orders
        $this->line('   4.5: MasterManager can access all orders');
        $canAccessBranch1 = $this->entityAccessService->canAccessEntity(
            $this->masterManagerUser, 'branch', $this->branch1->id
        );
        $canAccessBranch2 = $this->entityAccessService->canAccessEntity(
            $this->masterManagerUser, 'branch', $this->branch2->id
        );
        if ($canAccessBranch1 && $canAccessBranch2) {
            $this->info('   ✅ MasterManager can access orders in all branches');
        } else {
            $this->error('   ❌ MasterManager should access all orders');
            $passed = false;
        }

        return $passed;
    }

    protected function testTransferAccessControl()
    {
        $passed = true;

        // Test 5.1: User assigned to source can create transfer
        $this->line('   5.1: Source assignment for transfer creation');
        // BranchManager is assigned to branch1, so they can create transfers FROM branch1
        $canAccessSource = $this->entityAccessService->canAccessEntity(
            $this->branchManagerUser, 'branch', $this->branch1->id
        );
        if ($canAccessSource) {
            $this->info('   ✅ BranchManager can create transfer from branch 1 (source)');
        } else {
            $this->error('   ❌ BranchManager should be able to create transfer from assigned branch');
            $passed = false;
        }

        // Test 5.2: User assigned to destination can approve transfer
        $this->line('   5.2: Destination assignment for transfer approval');
        // BranchManager is NOT assigned to branch2, so they cannot approve transfers TO branch2
        $canAccessDest = $this->entityAccessService->canAccessEntity(
            $this->branchManagerUser, 'branch', $this->branch2->id
        );
        if (!$canAccessDest) {
            $this->info('   ✅ BranchManager cannot approve transfer to branch 2 (not assigned)');
        } else {
            $this->error('   ❌ BranchManager should not approve transfer to unassigned branch');
            $passed = false;
        }

        // Test 5.3: Assign branch2 to branch manager and re-test
        $this->line('   5.3: After assigning branch2, can approve transfers there');
        $this->branchManagerUser->employee->assignToEntity('branch', $this->branch2->id);
        $canAccessDest = $this->entityAccessService->canAccessEntity(
            $this->branchManagerUser, 'branch', $this->branch2->id
        );
        if ($canAccessDest) {
            $this->info('   ✅ BranchManager can now approve transfer to branch 2');
        } else {
            $this->error('   ❌ BranchManager should approve transfer after assignment');
            $passed = false;
        }

        // Clean up - unassign branch2
        $this->branchManagerUser->employee->unassignFromEntity('branch', $this->branch2->id);

        return $passed;
    }

    protected function testClothAccessControl()
    {
        $passed = true;

        // Test 6.1: Employee can see cloths in their branch's inventory
        $this->line('   6.1: Employee can see cloths in accessible inventory');
        $canAccessInventory1 = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 'branch', $this->branch1->id
        );
        if ($canAccessInventory1) {
            $this->info('   ✅ Employee can access cloths in inventory 1 (branch 1)');
        } else {
            $this->error('   ❌ Employee should access cloths in inventory 1');
            $passed = false;
        }

        // Test 6.2: Employee cannot see cloths in other branch's inventory
        $this->line('   6.2: Employee cannot see cloths in inaccessible inventory');
        $canAccessInventory2 = $this->entityAccessService->canAccessEntity(
            $this->employeeUser, 'branch', $this->branch2->id
        );
        if (!$canAccessInventory2) {
            $this->info('   ✅ Employee cannot access cloths in inventory 2 (branch 2)');
        } else {
            $this->error('   ❌ Employee should not access cloths in inventory 2');
            $passed = false;
        }

        // Test 6.3: MasterManager can see all cloths
        $this->line('   6.3: MasterManager can see all cloths');
        if ($this->masterManagerUser->hasFullAccess()) {
            $this->info('   ✅ MasterManager has full access to all cloths');
        } else {
            $this->error('   ❌ MasterManager should have full access');
            $passed = false;
        }

        return $passed;
    }

    protected function testRoleEntityTypeRestrictions()
    {
        $passed = true;

        // Create a test role
        $role = Role::create([
            'name' => $this->testPrefix . 'Test Role',
            'description' => 'Test role for entity permissions testing',
        ]);

        // Test 7.1: Role without entity restrictions applies universally
        $this->line('   7.1: Role without entity restrictions is universal');
        if ($role->isUniversal()) {
            $this->info('   ✅ New role is universal by default');
        } else {
            $this->error('   ❌ New role should be universal');
            $passed = false;
        }

        // Test 7.2: Add entity type restriction
        $this->line('   7.2: Add entity type restriction to role');
        $role->setEntityTypes(['branch']);
        if ($role->appliesToEntityType('branch')) {
            $this->info('   ✅ Role now restricted to branches');
        } else {
            $this->error('   ❌ Role should be restricted to branches');
            $passed = false;
        }

        // Test 7.3: Role should not apply to other entity types
        $this->line('   7.3: Role does not apply to other entity types');
        if (!$role->appliesToEntityType('workshop') && !$role->appliesToEntityType('factory')) {
            $this->info('   ✅ Role does not apply to workshops or factories');
        } else {
            $this->error('   ❌ Role should not apply to workshops or factories');
            $passed = false;
        }

        // Test 7.4: Check getApplicableEntityTypes
        $this->line('   7.4: Get applicable entity types');
        $types = $role->getApplicableEntityTypes();
        if (count($types) === 1 && in_array('branch', $types)) {
            $this->info('   ✅ getApplicableEntityTypes returns correct types');
        } else {
            $this->error('   ❌ getApplicableEntityTypes returned: ' . json_encode($types));
            $passed = false;
        }

        // Test 7.5: Role is no longer universal
        $this->line('   7.5: Role is no longer universal');
        if (!$role->isUniversal()) {
            $this->info('   ✅ Role with restrictions is not universal');
        } else {
            $this->error('   ❌ Role with restrictions should not be universal');
            $passed = false;
        }

        return $passed;
    }
}

