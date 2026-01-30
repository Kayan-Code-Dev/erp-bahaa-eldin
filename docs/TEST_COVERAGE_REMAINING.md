## Accounting Module

The Accounting Module handles cashbox management, transactions, expenses, and receivables. It integrates with orders, payments, and HR payroll.

### Cashbox Management

#### Test: List Cashboxes
- **Type**: Feature Test
- **Module**: Accounting (Cashboxes)
- **Endpoint**: `GET /api/v1/cashboxes`
- **Required Permission**: `cashbox.view`
- **Expected Status**: 200
- **Description**: List all cashboxes with filters (branch_id, is_active)
- **Should Pass For**: general_manager, accountant
- **Test Steps**: Create cashboxes, send GET request with filters
- **Assertions**: Response contains cashboxes with current_balance, filters work

#### Test: Show Cashbox
- **Type**: Feature Test
- **Module**: Accounting (Cashboxes)
- **Endpoint**: `GET /api/v1/cashboxes/{id}`
- **Required Permission**: `cashbox.view`
- **Expected Status**: 200
- **Test Steps**: Create cashbox, send GET request
- **Assertions**: Response contains cashbox details with current_balance

#### Test: Update Cashbox
- **Type**: Feature Test
- **Module**: Accounting (Cashboxes)
- **Endpoint**: `PUT /api/v1/cashboxes/{id}`
- **Required Permission**: `cashbox.manage`
- **Expected Status**: 200
- **Test Steps**: Create cashbox, update description, is_active
- **Assertions**: Cashbox is updated

#### Test: Get Cashbox Daily Summary
- **Type**: Feature Test
- **Module**: Accounting (Cashboxes)
- **Endpoint**: `GET /api/v1/cashboxes/{id}/daily-summary`
- **Required Permission**: `cashbox.view`
- **Expected Status**: 200
- **Test Steps**: Create cashbox with transactions, send GET request with date
- **Assertions**: Response contains daily summary (income, expenses, balance)

#### Test: Recalculate Cashbox Balance
- **Type**: Feature Test
- **Module**: Accounting (Cashboxes)
- **Endpoint**: `POST /api/v1/cashboxes/{id}/recalculate`
- **Required Permission**: `cashbox.manage`
- **Expected Status**: 200
- **Test Steps**: Create cashbox with transactions, recalculate
- **Assertions**: Balance is recalculated correctly

### Transactions

#### Test: List Transactions
- **Type**: Feature Test
- **Module**: Accounting (Transactions)
- **Endpoint**: `GET /api/v1/transactions`
- **Required Permission**: `transactions.view`
- **Expected Status**: 200
- **Description**: List transactions with filters (cashbox_id, type, category, date range, reference)
- **Should Pass For**: general_manager, accountant
- **Test Steps**: Create transactions, send GET request with filters
- **Assertions**: Response contains paginated transactions, filters work

#### Test: Show Transaction
- **Type**: Feature Test
- **Module**: Accounting (Transactions)
- **Endpoint**: `GET /api/v1/transactions/{id}`
- **Required Permission**: `transactions.view`
- **Expected Status**: 200
- **Test Steps**: Create transaction, send GET request
- **Assertions**: Response contains transaction details

#### Test: Reverse Transaction
- **Type**: Feature Test
- **Module**: Accounting (Transactions)
- **Endpoint**: `POST /api/v1/transactions/{id}/reverse`
- **Required Permission**: `cashbox.manage`
- **Expected Status**: 200
- **Description**: Reverse a transaction (creates reversal transaction)
- **Test Steps**: Create transaction, reverse it
- **Assertions**: Reversal transaction is created, cashbox balance is adjusted

#### Test: Get Cashbox Transactions
- **Type**: Feature Test
- **Module**: Accounting (Transactions)
- **Endpoint**: `GET /api/v1/cashboxes/{cashbox_id}/transactions`
- **Required Permission**: `transactions.view`
- **Expected Status**: 200
- **Test Steps**: Create cashbox with transactions, send GET request
- **Assertions**: Response contains only transactions for that cashbox

### Expenses

#### Test: List Expenses
- **Type**: Feature Test
- **Module**: Accounting (Expenses)
- **Endpoint**: `GET /api/v1/expenses`
- **Required Permission**: `expenses.view`
- **Expected Status**: 200
- **Description**: List expenses with filters (branch_id, cashbox_id, category, status, date range, vendor)
- **Should Pass For**: general_manager, accountant
- **Test Steps**: Create expenses, send GET request with filters
- **Assertions**: Response contains paginated expenses, filters work

#### Test: Create Expense
- **Type**: Feature Test
- **Module**: Accounting (Expenses)
- **Endpoint**: `POST /api/v1/expenses`
- **Required Permission**: `expenses.create`
- **Expected Status**: 201
- **Test Steps**: Create expense with required fields
- **Assertions**: Expense is created with status 'pending'

#### Test: Approve Expense
- **Type**: Feature Test
- **Module**: Accounting (Expenses)
- **Endpoint**: `POST /api/v1/expenses/{id}/approve`
- **Required Permission**: `expenses.approve`
- **Expected Status**: 200
- **Test Steps**: Create expense, approve it
- **Assertions**: Expense status changes to 'approved'

#### Test: Pay Expense
- **Type**: Feature Test
- **Module**: Accounting (Expenses)
- **Endpoint**: `POST /api/v1/expenses/{id}/pay`
- **Required Permission**: `expenses.pay`
- **Expected Status**: 200
- **Description**: Pay approved expense (creates transaction, updates cashbox)
- **Test Steps**: Create and approve expense, pay it
- **Assertions**: Expense status changes to 'paid', transaction is created, cashbox balance decreases

#### Test: Cancel Expense
- **Type**: Feature Test
- **Module**: Accounting (Expenses)
- **Endpoint**: `POST /api/v1/expenses/{id}/cancel`
- **Required Permission**: `expenses.delete`
- **Expected Status**: 200
- **Test Steps**: Create expense, cancel it
- **Assertions**: Expense status changes to 'cancelled'

### Receivables

#### Test: List Receivables
- **Type**: Feature Test
- **Module**: Accounting (Receivables)
- **Endpoint**: `GET /api/v1/receivables`
- **Required Permission**: `receivables.view`
- **Expected Status**: 200
- **Description**: List receivables (client debts) with filters (client_id, branch_id, order_id, status, overdue_only)
- **Should Pass For**: general_manager, accountant
- **Test Steps**: Create receivables, send GET request with filters
- **Assertions**: Response contains paginated receivables, filters work

#### Test: Create Receivable
- **Type**: Feature Test
- **Module**: Accounting (Receivables)
- **Endpoint**: `POST /api/v1/receivables`
- **Required Permission**: `receivables.manage`
- **Expected Status**: 201
- **Test Steps**: Create receivable (linked to order/client)
- **Assertions**: Receivable is created with status 'pending'

#### Test: Record Payment on Receivable
- **Type**: Feature Test
- **Module**: Accounting (Receivables)
- **Endpoint**: `POST /api/v1/receivables/{id}/record-payment`
- **Required Permission**: `receivables.manage`
- **Expected Status**: 200
- **Description**: Record payment on receivable (partial or full)
- **Test Steps**: Create receivable, record payment
- **Assertions**: Receivable paid_amount increases, status updates accordingly, transaction created

#### Test: Write Off Receivable
- **Type**: Feature Test
- **Module**: Accounting (Receivables)
- **Endpoint**: `POST /api/v1/receivables/{id}/write-off`
- **Required Permission**: `receivables.manage`
- **Expected Status**: 200
- **Test Steps**: Create receivable, write it off
- **Assertions**: Receivable status changes to 'written_off'

---

## HR Module

The HR Module manages departments, job titles, employees, attendance, custody, documents, deductions, and payroll. All operations are logged via ActivityLog.

### Departments

#### Test: List Departments
- **Type**: Feature Test
- **Module**: HR (Departments)
- **Endpoint**: `GET /api/v1/departments`
- **Required Permission**: `hr.departments.view`
- **Expected Status**: 200
- **Description**: List departments with filters (parent_id, is_active, with_children)
- **Should Pass For**: general_manager, hr_manager
- **Test Steps**: Create departments, send GET request
- **Assertions**: Response contains departments with hierarchy

#### Test: Create Department
- **Type**: Feature Test
- **Module**: HR (Departments)
- **Endpoint**: `POST /api/v1/departments`
- **Required Permission**: `hr.departments.manage`
- **Expected Status**: 201
- **Test Steps**: Create department
- **Assertions**: Department is created, ActivityLog is created

### Job Titles

#### Test: List Job Titles
- **Type**: Feature Test
- **Module**: HR (Job Titles)
- **Endpoint**: `GET /api/v1/job-titles`
- **Required Permission**: `hr.job-titles.view`
- **Expected Status**: 200
- **Test Steps**: Create job titles, send GET request
- **Assertions**: Response contains job titles

#### Test: Create Job Title
- **Type**: Feature Test
- **Module**: HR (Job Titles)
- **Endpoint**: `POST /api/v1/job-titles`
- **Required Permission**: `hr.job-titles.manage`
- **Expected Status**: 201
- **Test Steps**: Create job title
- **Assertions**: Job title is created

### Employees

#### Test: List Employees
- **Type**: Feature Test
- **Module**: HR (Employees)
- **Endpoint**: `GET /api/v1/employees`
- **Required Permission**: `hr.employees.view`
- **Expected Status**: 200
- **Description**: List employees with filters (department_id, job_title_id, branch_id, employment_type, employment_status)
- **Should Pass For**: general_manager, hr_manager
- **Test Steps**: Create employees, send GET request with filters
- **Assertions**: Response contains employees with relationships

#### Test: Create Employee
- **Type**: Feature Test
- **Module**: HR (Employees)
- **Endpoint**: `POST /api/v1/employees`
- **Required Permission**: `hr.employees.create`
- **Expected Status**: 201
- **Description**: Create employee (creates user, assigns roles, sets salary)
- **Test Steps**: Create employee with user credentials, roles, salary
- **Assertions**: Employee is created, User is created, roles are assigned, ActivityLog is created

#### Test: Update Employee
- **Type**: Feature Test
- **Module**: HR (Employees)
- **Endpoint**: `PUT /api/v1/employees/{id}`
- **Required Permission**: `hr.employees.update`
- **Expected Status**: 200
- **Test Steps**: Create employee, update salary/allowances
- **Assertions**: Employee is updated, ActivityLog is created

#### Test: Terminate Employee
- **Type**: Feature Test
- **Module**: HR (Employees)
- **Endpoint**: `POST /api/v1/employees/{id}/terminate`
- **Required Permission**: `hr.employees.terminate`
- **Expected Status**: 200
- **Test Steps**: Create employee, terminate with end_date
- **Assertions**: Employee employment_status changes to 'terminated', end_date is set

### Attendance

#### Test: Check In Employee
- **Type**: Feature Test
- **Module**: HR (Attendance)
- **Endpoint**: `POST /api/v1/attendance/check-in`
- **Required Permission**: `hr.attendance.check-in`
- **Expected Status**: 201
- **Test Steps**: Create employee, check in
- **Assertions**: Attendance record is created with check_in_time

#### Test: Check Out Employee
- **Type**: Feature Test
- **Module**: HR (Attendance)
- **Endpoint**: `POST /api/v1/attendance/check-out`
- **Required Permission**: `hr.attendance.check-in`
- **Expected Status**: 200
- **Description**: Check out employee (calculates hours_worked)
- **Test Steps**: Create attendance with check_in, check out
- **Assertions**: check_out_time is set, hours_worked is calculated

#### Test: List Attendance Records
- **Type**: Feature Test
- **Module**: HR (Attendance)
- **Endpoint**: `GET /api/v1/attendance`
- **Required Permission**: `hr.attendance.view`
- **Expected Status**: 200
- **Description**: List attendance with filters (employee_id, date, start_date, end_date, branch_id)
- **Test Steps**: Create attendance records, send GET request
- **Assertions**: Response contains attendance records

### Payroll

#### Test: Generate Payroll
- **Type**: Feature Test
- **Module**: HR (Payroll)
- **Endpoint**: `POST /api/v1/payrolls/generate`
- **Required Permission**: `hr.payroll.generate`
- **Expected Status**: 201
- **Description**: Generate payroll for period (calculates salary, allowances, deductions, overtime)
- **Test Steps**: Create employees with attendance, generate payroll
- **Assertions**: Payroll is created, PayrollItems are created, totals are calculated

#### Test: Approve Payroll
- **Type**: Feature Test
- **Module**: HR (Payroll)
- **Endpoint**: `POST /api/v1/payrolls/{id}/approve`
- **Required Permission**: `hr.payroll.approve`
- **Expected Status**: 200
- **Test Steps**: Create payroll, approve it
- **Assertions**: Payroll status changes to 'approved'

#### Test: Process Payroll
- **Type**: Feature Test
- **Module**: HR (Payroll)
- **Endpoint**: `POST /api/v1/payrolls/{id}/process`
- **Required Permission**: `hr.payroll.process`
- **Expected Status**: 200
- **Description**: Process approved payroll (creates transactions, updates cashbox)
- **Test Steps**: Create and approve payroll, process it
- **Assertions**: Payroll status changes to 'processed', transactions are created, cashbox balance decreases

### Employee Custody

#### Test: Assign Custody to Employee
- **Type**: Feature Test
- **Module**: HR (Employee Custody)
- **Endpoint**: `POST /api/v1/employee-custodies`
- **Required Permission**: `hr.custody.assign`
- **Expected Status**: 201
- **Description**: Assign custody item to employee (laptop, phone, tools, etc.)
- **Test Steps**: Create employee, assign custody
- **Assertions**: Custody is assigned with status 'assigned', assigned_date is set

#### Test: Return Custody
- **Type**: Feature Test
- **Module**: HR (Employee Custody)
- **Endpoint**: `POST /api/v1/employee-custodies/{id}/return`
- **Required Permission**: `hr.custody.return`
- **Expected Status**: 200
- **Test Steps**: Create assigned custody, return it
- **Assertions**: Custody status changes to 'returned', returned_date is set

### Employee Documents

#### Test: Upload Employee Document
- **Type**: Feature Test
- **Module**: HR (Employee Documents)
- **Endpoint**: `POST /api/v1/employee-documents`
- **Required Permission**: `hr.documents.upload`
- **Expected Status**: 201
- **Description**: Upload document for employee (ID, contract, etc.)
- **Test Steps**: Create employee, upload document
- **Assertions**: Document is uploaded, file is stored

#### Test: Verify Document
- **Type**: Feature Test
- **Module**: HR (Employee Documents)
- **Endpoint**: `POST /api/v1/employee-documents/{id}/verify`
- **Required Permission**: `hr.documents.verify`
- **Expected Status**: 200
- **Test Steps**: Create document, verify it
- **Assertions**: Document is_verified changes to true, verified_at is set

### Deductions

#### Test: Create Deduction
- **Type**: Feature Test
- **Module**: HR (Deductions)
- **Endpoint**: `POST /api/v1/deductions`
- **Required Permission**: `hr.deductions.manage`
- **Expected Status**: 201
- **Description**: Create deduction for employee (linked to payroll)
- **Test Steps**: Create employee, payroll, create deduction
- **Assertions**: Deduction is created

---

## Reports Module

The Reports Module provides various reports for orders, financials, inventory, performance, and HR.

#### Test: Daily Cashbox Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/daily-cashbox`
- **Required Permission**: `reports.financial`
- **Expected Status**: 200
- **Description**: Get daily cashbox report for date
- **Should Pass For**: general_manager, accountant
- **Test Steps**: Create cashbox with transactions, send GET request with date
- **Assertions**: Response contains daily summary

#### Test: Monthly Financial Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/monthly-financial`
- **Required Permission**: `reports.financial`
- **Expected Status**: 200
- **Test Steps**: Create transactions, send GET request with month
- **Assertions**: Response contains monthly financial summary

#### Test: Debts Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/debts`
- **Required Permission**: `reports.financial`
- **Expected Status**: 200
- **Description**: Get receivables/debts report with filters
- **Test Steps**: Create receivables, send GET request
- **Assertions**: Response contains debts report

#### Test: Expenses Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/expenses`
- **Required Permission**: `reports.financial`
- **Expected Status**: 200
- **Test Steps**: Create expenses, send GET request with filters
- **Assertions**: Response contains expenses report

#### Test: Available Dresses Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/available-dresses`
- **Required Permission**: `reports.view`
- **Expected Status**: 200
- **Test Steps**: Create clothes with different statuses, send GET request
- **Assertions**: Response contains available dresses

#### Test: Most Rented Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/most-rented`
- **Required Permission**: `reports.view`
- **Expected Status**: 200
- **Test Steps**: Create rental orders, send GET request
- **Assertions**: Response contains most rented items

#### Test: Employee Orders Report
- **Type**: Feature Test
- **Module**: Reports
- **Endpoint**: `GET /api/v1/reports/employee-orders`
- **Required Permission**: `reports.performance`
- **Expected Status**: 200
- **Description**: Get orders by employee report
- **Test Steps**: Create orders with created_by, send GET request
- **Assertions**: Response contains employee orders statistics

---

## Notifications Module

The Notifications Module handles system notifications for users.

#### Test: List Notifications (User's Own)
- **Type**: Feature Test
- **Module**: Notifications
- **Endpoint**: `GET /api/v1/notifications`
- **Required Permission**: None (users see their own)
- **Expected Status**: 200
- **Description**: List authenticated user's notifications
- **Test Steps**: Create notifications for user, send GET request
- **Assertions**: Response contains user's notifications

#### Test: Get Unread Count
- **Type**: Feature Test
- **Module**: Notifications
- **Endpoint**: `GET /api/v1/notifications/unread-count`
- **Required Permission**: None
- **Expected Status**: 200
- **Test Steps**: Create unread notifications, send GET request
- **Assertions**: Response contains unread count

#### Test: Mark Notification as Read
- **Type**: Feature Test
- **Module**: Notifications
- **Endpoint**: `POST /api/v1/notifications/{id}/read`
- **Required Permission**: None (users mark their own)
- **Expected Status**: 200
- **Test Steps**: Create notification, mark as read
- **Assertions**: Notification is_read changes to true, read_at is set

#### Test: Broadcast Notification
- **Type**: Feature Test
- **Module**: Notifications
- **Endpoint**: `POST /api/v1/notifications/broadcast`
- **Required Permission**: `notifications.manage`
- **Expected Status**: 201
- **Description**: Broadcast notification to all users or specific roles
- **Should Pass For**: general_manager
- **Test Steps**: Send POST request with message and target (all/roles)
- **Assertions**: Notifications are created for all target users

#### Test: Mark All as Read
- **Type**: Feature Test
- **Module**: Notifications
- **Endpoint**: `POST /api/v1/notifications/mark-all-read`
- **Required Permission**: None
- **Expected Status**: 200
- **Test Steps**: Create multiple unread notifications, mark all as read
- **Assertions**: All user's notifications are marked as read

---

## User & Role Management Module

The User & Role Management Module handles users, roles, and permissions.

### Users

#### Test: List Users
- **Type**: Feature Test
- **Module**: User Management
- **Endpoint**: `GET /api/v1/users`
- **Required Permission**: `users.view`
- **Expected Status**: 200
- **Description**: List all users with filters
- **Should Pass For**: general_manager, hr_manager
- **Test Steps**: Create users, send GET request
- **Assertions**: Response contains users

#### Test: Create User
- **Type**: Feature Test
- **Module**: User Management
- **Endpoint**: `POST /api/v1/users`
- **Required Permission**: `users.create`
- **Expected Status**: 201
- **Test Steps**: Create user with email, password, roles
- **Assertions**: User is created, roles are assigned

#### Test: Update User
- **Type**: Feature Test
- **Module**: User Management
- **Endpoint**: `PUT /api/v1/users/{id}`
- **Required Permission**: `users.update`
- **Expected Status**: 200
- **Test Steps**: Create user, update name/email
- **Assertions**: User is updated

#### Test: Get User Permissions
- **Type**: Feature Test
- **Module**: User Management
- **Endpoint**: `GET /api/v1/users/{id}/permissions`
- **Required Permission**: `users.view`
- **Expected Status**: 200
- **Test Steps**: Create user with roles/permissions, send GET request
- **Assertions**: Response contains user's permissions (from roles)

### Roles

#### Test: List Roles
- **Type**: Feature Test
- **Module**: Role Management
- **Endpoint**: `GET /api/v1/roles`
- **Required Permission**: `roles.view`
- **Expected Status**: 200
- **Test Steps**: Create roles, send GET request
- **Assertions**: Response contains roles

#### Test: Create Role
- **Type**: Feature Test
- **Module**: Role Management
- **Endpoint**: `POST /api/v1/roles`
- **Required Permission**: `roles.create`
- **Expected Status**: 201
- **Test Steps**: Create role with name, description
- **Assertions**: Role is created

#### Test: Assign Permissions to Role
- **Type**: Feature Test
- **Module**: Role Management
- **Endpoint**: `PUT /api/v1/roles/{id}/permissions`
- **Required Permission**: `roles.assign-permissions`
- **Expected Status**: 200
- **Description**: Assign permissions to role
- **Test Steps**: Create role and permissions, assign permissions
- **Assertions**: Permissions are assigned to role

### Permissions

#### Test: List Permissions
- **Type**: Feature Test
- **Module**: Permission Management
- **Endpoint**: `GET /api/v1/permissions`
- **Required Permission**: `roles.view`
- **Expected Status**: 200
- **Test Steps**: Send GET request
- **Assertions**: Response contains all permissions

#### Test: Get My Permissions
- **Type**: Feature Test
- **Module**: Permission Management
- **Endpoint**: `GET /api/v1/me/permissions`
- **Required Permission**: None (users see their own)
- **Expected Status**: 200
- **Test Steps**: Authenticate user with roles, send GET request
- **Assertions**: Response contains user's permissions

---

## Dashboard Module

The Dashboard Module provides aggregated data and metrics for the system.

#### Test: Get Main Dashboard
- **Type**: Feature Test
- **Module**: Dashboard
- **Endpoint**: `GET /api/v1/dashboard`
- **Required Permission**: `dashboard.view`
- **Expected Status**: 200
- **Description**: Get main dashboard with key metrics
- **Should Pass For**: general_manager, reception_employee, sales_employee
- **Test Steps**: Create orders, clients, payments, send GET request
- **Assertions**: Response contains dashboard metrics (orders, clients, revenue, etc.)

#### Test: Get Financial Dashboard
- **Type**: Feature Test
- **Module**: Dashboard
- **Endpoint**: `GET /api/v1/dashboard/financial`
- **Required Permission**: `dashboard.view` or `reports.financial`
- **Expected Status**: 200
- **Test Steps**: Create transactions, send GET request
- **Assertions**: Response contains financial metrics

#### Test: Get HR Dashboard - Attendance Metrics
- **Type**: Feature Test
- **Module**: Dashboard
- **Endpoint**: `GET /api/v1/dashboard/hr/attendance`
- **Required Permission**: `dashboard.view` or `hr.attendance.reports`
- **Expected Status**: 200
- **Test Steps**: Create attendance records, send GET request
- **Assertions**: Response contains attendance metrics

#### Test: Get HR Dashboard - Payroll Metrics
- **Type**: Feature Test
- **Module**: Dashboard
- **Endpoint**: `GET /api/v1/dashboard/hr/payroll`
- **Required Permission**: `dashboard.view` or `hr.payroll.view`
- **Expected Status**: 200
- **Test Steps**: Create payrolls, send GET request
- **Assertions**: Response contains payroll metrics

---

*This comprehensive test coverage document provides detailed test scenarios for all modules in the Atelier Management System. Each test scenario includes type, module, endpoint, permissions, expected status, description, test steps, and assertions. The document serves as a complete specification for implementing comprehensive test coverage across the entire system.*

