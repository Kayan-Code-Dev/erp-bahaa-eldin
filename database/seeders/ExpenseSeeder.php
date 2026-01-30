<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates realistic expenses for cashboxes.
     * Only creates expenses if system is empty (no existing expenses).
     */
    public function run(): void
    {
        // Check if expenses already exist
        if (Expense::count() > 0) {
            $this->command->info('Expenses already exist. Skipping expense seeder.');
            return;
        }

        $this->command->info('Creating expenses...');
        $this->command->newLine();

        // Get cashboxes with branches
        $cashboxes = Cashbox::with('branch')->where('is_active', true)->get();

        if ($cashboxes->isEmpty()) {
            $this->command->warn('No active cashboxes found. Please run CashboxSeeder first.');
            return;
        }

        // Get users for created_by and approved_by
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        // Define realistic expense data
        $expenseTemplates = [
            // Rent expenses
            [
                'category' => Expense::CATEGORY_RENT,
                'subcategory' => 'Monthly Rent',
                'amount' => 15000.00,
                'vendor' => 'Building Owner',
                'description' => 'Monthly rent payment for branch location',
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_RENT,
                'subcategory' => 'Warehouse Rent',
                'amount' => 5000.00,
                'vendor' => 'Storage Facility',
                'description' => 'Monthly warehouse storage rent',
                'status' => Expense::STATUS_PAID,
            ],

            // Utilities expenses
            [
                'category' => Expense::CATEGORY_UTILITIES,
                'subcategory' => 'Electricity',
                'amount' => 2500.00,
                'vendor' => 'Electricity Company',
                'description' => 'Monthly electricity bill',
                'reference_number' => 'ELC-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_UTILITIES,
                'subcategory' => 'Water',
                'amount' => 800.00,
                'vendor' => 'Water Authority',
                'description' => 'Monthly water bill',
                'reference_number' => 'WTR-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_APPROVED,
            ],
            [
                'category' => Expense::CATEGORY_UTILITIES,
                'subcategory' => 'Internet & Phone',
                'amount' => 1200.00,
                'vendor' => 'Telecom Company',
                'description' => 'Monthly internet and phone services',
                'reference_number' => 'TEL-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PAID,
            ],

            // Supplies expenses
            [
                'category' => Expense::CATEGORY_SUPPLIES,
                'subcategory' => 'Fabric Materials',
                'amount' => 3500.00,
                'vendor' => 'Fabric Supplier',
                'description' => 'Purchase of fabric materials for tailoring',
                'reference_number' => 'INV-FAB-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_SUPPLIES,
                'subcategory' => 'Thread & Accessories',
                'amount' => 850.00,
                'vendor' => 'Craft Supplies',
                'description' => 'Thread, buttons, zippers, and other accessories',
                'reference_number' => 'INV-THR-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_APPROVED,
            ],
            [
                'category' => Expense::CATEGORY_SUPPLIES,
                'subcategory' => 'Office Supplies',
                'amount' => 450.00,
                'vendor' => 'Office Depot',
                'description' => 'Paper, pens, folders, and office materials',
                'reference_number' => 'INV-OFF-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PENDING,
            ],

            // Maintenance expenses
            [
                'category' => Expense::CATEGORY_MAINTENANCE,
                'subcategory' => 'Sewing Machine Repair',
                'amount' => 1200.00,
                'vendor' => 'Equipment Repair Service',
                'description' => 'Repair and maintenance of sewing machines',
                'reference_number' => 'MNT-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_MAINTENANCE,
                'subcategory' => 'Building Maintenance',
                'amount' => 2800.00,
                'vendor' => 'Building Maintenance Co.',
                'description' => 'General building repairs and maintenance',
                'reference_number' => 'MNT-BLD-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_APPROVED,
            ],

            // Salaries expenses
            [
                'category' => Expense::CATEGORY_SALARIES,
                'subcategory' => 'Employee Salaries',
                'amount' => 25000.00,
                'vendor' => 'Payroll Department',
                'description' => 'Monthly employee salary payments',
                'reference_number' => 'PAY-' . date('Y') . '-' . date('m'),
                'status' => Expense::STATUS_PAID,
            ],

            // Marketing expenses
            [
                'category' => Expense::CATEGORY_MARKETING,
                'subcategory' => 'Social Media Advertising',
                'amount' => 1500.00,
                'vendor' => 'Social Media Platform',
                'description' => 'Facebook and Instagram advertising campaign',
                'reference_number' => 'ADV-SOC-' . date('Ymd'),
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_MARKETING,
                'subcategory' => 'Print Advertising',
                'amount' => 800.00,
                'vendor' => 'Print Media Company',
                'description' => 'Flyers and brochure printing',
                'reference_number' => 'ADV-PRT-' . date('Ymd'),
                'status' => Expense::STATUS_PENDING,
            ],

            // Transport expenses
            [
                'category' => Expense::CATEGORY_TRANSPORT,
                'subcategory' => 'Delivery Vehicle Fuel',
                'amount' => 1200.00,
                'vendor' => 'Gas Station',
                'description' => 'Fuel for delivery vehicles',
                'reference_number' => 'FUEL-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_TRANSPORT,
                'subcategory' => 'Vehicle Maintenance',
                'amount' => 1800.00,
                'vendor' => 'Auto Service Center',
                'description' => 'Vehicle service and oil change',
                'reference_number' => 'AUTO-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_APPROVED,
            ],

            // Cleaning expenses
            [
                'category' => Expense::CATEGORY_CLEANING,
                'subcategory' => 'Professional Cleaning Service',
                'amount' => 900.00,
                'vendor' => 'Cleaning Company',
                'description' => 'Monthly professional cleaning service',
                'reference_number' => 'CLN-' . date('Y') . '-' . date('m'),
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_CLEANING,
                'subcategory' => 'Cleaning Supplies',
                'amount' => 350.00,
                'vendor' => 'Supply Store',
                'description' => 'Cleaning chemicals and supplies',
                'reference_number' => 'CLN-SUP-' . date('Ymd'),
                'status' => Expense::STATUS_PENDING,
            ],

            // Other expenses
            [
                'category' => Expense::CATEGORY_OTHER,
                'subcategory' => 'Bank Fees',
                'amount' => 150.00,
                'vendor' => 'Bank',
                'description' => 'Monthly bank service charges',
                'reference_number' => 'BANK-FEE-' . date('Y') . date('m'),
                'status' => Expense::STATUS_PAID,
            ],
            [
                'category' => Expense::CATEGORY_OTHER,
                'subcategory' => 'Legal Consultation',
                'amount' => 2500.00,
                'vendor' => 'Law Firm',
                'description' => 'Legal consultation and document review',
                'reference_number' => 'LEG-' . date('Ymd') . '-001',
                'status' => Expense::STATUS_APPROVED,
            ],
            [
                'category' => Expense::CATEGORY_OTHER,
                'subcategory' => 'Insurance Premium',
                'amount' => 3200.00,
                'vendor' => 'Insurance Company',
                'description' => 'Annual business insurance premium',
                'reference_number' => 'INS-' . date('Y') . '-001',
                'status' => Expense::STATUS_PAID,
            ],
        ];

        // Generate dates for expenses (mix of recent and past dates)
        $dates = [];
        for ($i = 0; $i < 30; $i++) {
            $dates[] = now()->subDays(rand(0, 60));
        }

        $created = 0;
        $cashboxIndex = 0;
        
        // Create at least 10 expenses
        for ($i = 0; $i < max(10, count($expenseTemplates)); $i++) {
            $template = $expenseTemplates[$i % count($expenseTemplates)];
            $cashbox = $cashboxes[$cashboxIndex % $cashboxes->count()];
            $cashboxIndex++;

            // Clone template and add variation
            $expenseData = array_merge($template, [
                'cashbox_id' => $cashbox->id,
                'branch_id' => $cashbox->branch_id,
                'amount' => $template['amount'] * (0.8 + (rand(0, 40) / 100)), // Vary amount by ±20%
                'expense_date' => $dates[array_rand($dates)],
                'created_by' => $users->random()->id,
                'approved_by' => in_array($template['status'], [Expense::STATUS_APPROVED, Expense::STATUS_PAID]) 
                    ? $users->random()->id 
                    : null,
                'approved_at' => in_array($template['status'], [Expense::STATUS_APPROVED, Expense::STATUS_PAID])
                    ? now()->subDays(rand(1, 10))
                    : null,
                'notes' => rand(0, 1) ? 'Important expense - review quarterly reports' : null,
            ]);

            Expense::create($expenseData);
            $created++;

            $statusIcon = match($expenseData['status']) {
                Expense::STATUS_PAID => '✓',
                Expense::STATUS_APPROVED => '◐',
                Expense::STATUS_PENDING => '○',
                default => '✗',
            };

            $this->command->info(sprintf(
                "  %s Created expense: %s - %s (Amount: %s) for %s [%s]",
                $statusIcon,
                $expenseData['category'],
                $expenseData['subcategory'],
                number_format($expenseData['amount'], 2),
                $cashbox->branch->name,
                $expenseData['status']
            ));
        }

        $this->command->newLine();
        $this->command->info("✓ Successfully created {$created} expenses.");
        
        // Show summary
        $this->command->newLine();
        $this->command->info('Expense Summary:');
        $statusCounts = DB::table('expenses')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');
        
        foreach ($statusCounts as $status => $count) {
            $this->command->info("  - {$status}: {$count}");
        }
    }
}

