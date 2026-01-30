# ERP Bhaa Backend API Documentation

## Overview

This is a comprehensive ERP (Enterprise Resource Planning) system backend built with Laravel 12, featuring multi-tenant architecture with different user roles and permissions. The system supports inventory management, employee management, branch operations, and role-based access control.

## Base URL
```
http://your-domain.com/api/v1/web
```

## Authentication

The API uses Laravel Passport for authentication with different guards for different user types:
- `admin-api` - For system administrators
- `branchManager-api` - For branch managers
- `branch-api` - For branch users
- `employee-api` - For employees

All authenticated endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {access_token}
```

## User Types & Permissions

### 1. Admin
- Full system access
- Can manage all users, branches, and system settings
- Can assign roles and permissions

### 2. Branch Manager
- Manages multiple branches
- Can create and manage employees within their branches
- Can manage inventory transfers

### 3. Branch
- Manages single branch operations
- Can manage departments, jobs, and employees
- Can manage inventory and categories

### 4. Employee
- Limited access based on assigned permissions
- Can manage inventory and perform basic operations

## API Endpoints

### Authentication Endpoints

#### Login
```http
POST /api/v1/web/auth/login
```

**Request Body:**
```json
{
    "guard": "admin-api|branchManager-api|branch-api|employee-api",
    "emOrMb": "email_or_mobile",
    "password": "password",
    "fcm_token": "optional_fcm_token"
}
```

**Response:**
```json
{
    "status": true,
    "message": "تم تسجيل الدخول بنجاح",
    "data": {
        "user": {
            "id": 1,
            "name": "User Name",
            "email": "user@example.com",
            "phone": "1234567890"
        },
        "access_token": "bearer_token_here",
        "token_type": "Bearer"
    }
}
```

#### Logout
```http
GET /api/v1/web/auth/logout
```

**Headers:**
```
Authorization: Bearer {access_token}
```

#### Forgot Password
```http
POST /api/v1/web/auth/forgot-password
```

**Request Body:**
```json
{
    "guard": "admin-api|branchManager-api|branch-api|employee-api",
    "emOrMb": "email_or_mobile"
}
```

#### Send Code for Password Reset
```http
POST /api/v1/web/auth/send-code-forgot-password
```

#### Check Forgot Password Code
```http
POST /api/v1/web/auth/check-forgot-password
```

**Request Body:**
```json
{
    "guard": "admin-api|branchManager-api|branch-api|employee-api",
    "emOrMb": "email_or_mobile",
    "code": "123456"
}
```

#### Reset Password
```http
POST /api/v1/web/auth/reset-password
```

**Request Body:**
```json
{
    "guard": "admin-api|branchManager-api|branch-api|employee-api",
    "emOrMb": "email_or_mobile",
    "password": "new_password",
    "password_confirmation": "new_password"
}
```

#### Update Password
```http
POST /api/v1/web/auth/update-password
```

**Headers:**
```
Authorization: Bearer {access_token}
```

**Request Body:**
```json
{
    "current_password": "current_password",
    "new_password": "new_password",
    "new_password_confirmation": "new_password"
}
```

## Admin Endpoints

All admin endpoints require authentication with `admin-api` guard.

### Roles & Permissions

#### Get All Roles
```http
GET /api/v1/web/admins/roles
```

#### Create Role
```http
POST /api/v1/web/admins/roles
```

**Request Body:**
```json
{
    "name": "role_name",
    "display_name": "Role Display Name",
    "description": "Role description"
}
```

#### Update Role
```http
PUT /api/v1/web/admins/roles/{role_id}
```

#### Delete Role
```http
DELETE /api/v1/web/admins/roles/{role_id}
```

#### Get Role Details
```http
GET /api/v1/web/admins/roles/{role_id}
```

#### Get All Permissions
```http
GET /api/v1/web/admins/permissions
```

#### Toggle Permission for Role
```http
POST /api/v1/web/admins/permissions/role
```

**Request Body:**
```json
{
    "role_id": 1,
    "permission_id": 1,
    "grant": true
}
```

### Countries Management

#### Get All Countries
```http
GET /api/v1/web/admins/countries
```

#### Create Country
```http
POST /api/v1/web/admins/countries
```

**Request Body:**
```json
{
    "name": "Country Name",
    "code": "CN",
    "phone_code": "+1"
}
```

#### Update Country
```http
PUT /api/v1/web/admins/countries/{country_id}
```

#### Delete Country
```http
DELETE /api/v1/web/admins/countries/{country_id}
```

### Cities Management

#### Get All Cities
```http
GET /api/v1/web/admins/cities
```

#### Create City
```http
POST /api/v1/web/admins/cities
```

**Request Body:**
```json
{
    "name": "City Name",
    "country_id": 1
}
```

#### Update City
```http
PUT /api/v1/web/admins/cities/{city_id}
```

#### Delete City
```http
DELETE /api/v1/web/admins/cities/{city_id}
```

### Admin Management

#### Get All Admins
```http
GET /api/v1/web/admins/admins
```

#### Get Admin Roles
```http
GET /api/v1/web/admins/get_role_admin
```

#### Create Admin
```http
POST /api/v1/web/admins/admins
```

**Request Body:**
```json
{
    "role_id": 1,
    "first_name": "First Name",
    "last_name": "Last Name",
    "email": "admin@example.com",
    "phone": "1234567890",
    "id_number": "123456789",
    "country_id": 1,
    "city_id": 1,
    "image": "file_upload"
}
```

#### Update Admin
```http
PUT /api/v1/web/admins/admins/{admin_uuid}
```

#### Delete Admin
```http
DELETE /api/v1/web/admins/admins/{admin_uuid}
```

#### Get Deleted Admins
```http
GET /api/v1/web/admins/get_deleted_admins
```

#### Restore Admin
```http
GET /api/v1/web/admins/restore_admin/{admin_uuid}
```

#### Force Delete Admin
```http
DELETE /api/v1/web/admins/force_delete_admin/{admin_uuid}
```

#### Block Admin
```http
POST /api/v1/web/admins/block_admin/{admin_uuid}
```

### Branch Manager Management

#### Get All Branch Managers
```http
GET /api/v1/web/admins/branch-managers
```

#### Get Branch Manager Roles
```http
GET /api/v1/web/admins/get_role_branch_manager
```

#### Create Branch Manager
```http
POST /api/v1/web/admins/branch-managers
```

**Request Body:**
```json
{
    "role_id": 1,
    "first_name": "First Name",
    "last_name": "Last Name",
    "email": "manager@example.com",
    "phone": "1234567890",
    "id_number": "123456789",
    "country_id": 1,
    "city_id": 1,
    "image": "file_upload"
}
```

#### Update Branch Manager
```http
PUT /api/v1/web/admins/branch-managers/{branch_manager_uuid}
```

#### Delete Branch Manager
```http
DELETE /api/v1/web/admins/branch-managers/{branch_manager_uuid}
```

#### Get Deleted Branch Managers
```http
GET /api/v1/web/admins/get_deleted_branchManagers
```

#### Restore Branch Manager
```http
GET /api/v1/web/admins/restore_branchManager/{branch_manager_uuid}
```

#### Force Delete Branch Manager
```http
DELETE /api/v1/web/admins/force_delete_branchManager/{branch_manager_uuid}
```

#### Block Branch Manager
```http
POST /api/v1/web/admins/block_branchManager/{branch_manager_uuid}
```

### Branch Management

#### Get All Branches
```http
GET /api/v1/web/admins/branches
```

#### Get Branch Managers for Branch Creation
```http
GET /api/v1/web/admins/branches/branch_Manger
```

#### Create Branch
```http
POST /api/v1/web/admins/branches
```

**Request Body:**
```json
{
    "branch_manager_id": 1,
    "name": "Branch Name",
    "email": "branch@example.com",
    "phone": "1234567890",
    "location": "Branch Address",
    "latitude": 25.2048,
    "longitude": 55.2708
}
```

#### Update Branch
```http
PUT /api/v1/web/admins/branches/{branch_uuid}
```

#### Delete Branch
```http
DELETE /api/v1/web/admins/branches/{branch_uuid}
```

#### Get Deleted Branches
```http
GET /api/v1/web/admins/branches/get_deleted_branches
```

#### Restore Branch
```http
GET /api/v1/web/admins/branches/restore_branches/{branch_uuid}
```

#### Force Delete Branch
```http
DELETE /api/v1/web/admins/branches/force_delete_branches/{branch_uuid}
```

#### Block Branch
```http
POST /api/v1/web/admins/branches/block_branches/{branch_uuid}
```

## Branch Manager Endpoints

All branch manager endpoints require authentication with `branchManager-api` guard.

### Role & Permission Management

#### Get My Roles
```http
GET /api/v1/web/branch_managers/my_roles_branch_managers
```

#### Get Role Details
```http
GET /api/v1/web/branch_managers/role/{role_id}
```

#### Get My Permissions
```http
GET /api/v1/web/branch_managers/my_permissions_branch_managers
```

#### Toggle Permission
```http
POST /api/v1/web/branch_managers/togglePermission
```

### Branch Management

#### Get My Branches
```http
GET /api/v1/web/branch_managers/get_my_branches
```

#### Create Branch
```http
POST /api/v1/web/branch_managers/create_branches
```

**Request Body:**
```json
{
    "name": "Branch Name",
    "email": "branch@example.com",
    "phone": "1234567890",
    "location": "Branch Address",
    "latitude": 25.2048,
    "longitude": 55.2708
}
```

#### Update Branch
```http
PUT /api/v1/web/branch_managers/update_branches/{branch_uuid}
```

#### Delete Branch
```http
DELETE /api/v1/web/branch_managers/delete_branches/{branch_uuid}
```

#### Get Deleted My Branches
```http
GET /api/v1/web/branch_managers/get_deleted_my_branches
```

#### Restore My Branch
```http
GET /api/v1/web/branch_managers/restore_my_branches/{branch_uuid}
```

#### Force Delete My Branch
```http
DELETE /api/v1/web/branch_managers/force_delete_my_branches/{branch_uuid}
```

#### Block My Branch
```http
POST /api/v1/web/branch_managers/block_my_branches/{branch_uuid}
```

### Employee Management

#### Get Employees
```http
GET /api/v1/web/branch_managers/employees/get_employees
```

#### Get Branches
```http
GET /api/v1/web/branch_managers/employees/get_branches
```

#### Get Branch Departments
```http
GET /api/v1/web/branch_managers/employees/get_branches_department/{branch_id}
```

#### Get Branch Jobs
```http
GET /api/v1/web/branch_managers/employees/get_branches_job/{department_id}
```

#### Get Countries
```http
GET /api/v1/web/branch_managers/employees/get_countries
```

#### Get Cities by Country
```http
GET /api/v1/web/branch_managers/employees/get_cities_by_country/{country_id}
```

#### Get Role for Branch
```http
GET /api/v1/web/branch_managers/employees/get_role_branch/{branch_id}
```

#### Create Employee
```http
POST /api/v1/web/branch_managers/employees/create_employee
```

**Request Body:**
```json
{
    "full_name": "Employee Full Name",
    "branch_id": 1,
    "phone": "1234567890",
    "department_id": 1,
    "country_id": 1,
    "city_id": 1,
    "national_id": "123456789",
    "branch_job_id": 1,
    "role_id": 1,
    "username": "employee_username",
    "email": "employee@example.com",
    "mobile": "1234567890",
    "salary": 5000.00,
    "hire_date": "2024-01-01",
    "commission": 500.00,
    "contract_end_date": "2024-12-31",
    "fingerprint_device_number": "DEV001",
    "work_from": "09:00",
    "work_to": "17:00"
}
```

#### Update Employee
```http
PUT /api/v1/web/branch_managers/employees/update_employees/{employee_uuid}
```

#### Delete Employee
```http
DELETE /api/v1/web/branch_managers/employees/delete_employee/{employee_uuid}
```

#### Get Deleted Employees
```http
GET /api/v1/web/branch_managers/employees/get_deleted_employees
```

#### Restore Employee
```http
GET /api/v1/web/branch_managers/employees/restore_employees/{employee_uuid}
```

#### Force Delete Employee
```http
DELETE /api/v1/web/branch_managers/employees/force_delete_employees/{employee_uuid}
```

#### Block Employee
```http
POST /api/v1/web/branch_managers/employees/block_employees/{employee_uuid}
```

### Inventory Management

#### Get Inventories
```http
GET /api/v1/web/branch_managers/inventories
```

### Inventory Transfer Management

#### Get Inventory Transfers
```http
GET /api/v1/web/branch_managers/inventory_transfers
```

#### Get Branches for Transfer
```http
GET /api/v1/web/branch_managers/inventory_transfers/get_branches
```

#### Get Categories for Transfer
```http
GET /api/v1/web/branch_managers/inventory_transfers/ge_category/{branch_id}
```

#### Get Sub Categories
```http
GET /api/v1/web/branch_managers/inventory_transfers/get_sub_category_by_categories/{category_id}
```

#### Create Inventory Transfer
```http
POST /api/v1/web/branch_managers/inventory_transfers
```

**Request Body:**
```json
{
    "from_branch_id": 1,
    "to_branch_id": 2,
    "inventory_id": 1,
    "quantity": 10,
    "notes": "Transfer notes"
}
```

#### Approve Transfer
```http
POST /api/v1/web/branch_managers/inventory_transfers/{transfer_uuid}/approve
```

#### Reject Transfer
```http
POST /api/v1/web/branch_managers/inventory_transfers/{transfer_uuid}/reject
```

## Branch Endpoints

All branch endpoints require authentication with `branch-api` guard.

### Role & Permission Management

#### Get My Roles
```http
GET /api/v1/web/branches/my_roles_branches
```

#### Get Role Details
```http
GET /api/v1/web/branches/role/{role_id}
```

#### Create Role for Branch
```http
POST /api/v1/web/branches/create_roles_branch
```

#### Get My Permissions
```http
GET /api/v1/web/branches/my_permissions_branches
```

#### Toggle Permission
```http
POST /api/v1/web/branches/togglePermission
```

### Department Management

#### Get Departments
```http
GET /api/v1/web/branches/departments
```

#### Create Department
```http
POST /api/v1/web/branches/departments
```

**Request Body:**
```json
{
    "name": "Department Name",
    "code": "DEPT001",
    "description": "Department description"
}
```

#### Update Department
```http
PUT /api/v1/web/branches/departments/{department_id}
```

#### Delete Department
```http
DELETE /api/v1/web/branches/departments/{department_id}
```

### Job Management

#### Get Departments for Job
```http
GET /api/v1/web/branches/jobs/get_department
```

#### Get Jobs
```http
GET /api/v1/web/branches/jobs
```

#### Create Job
```http
POST /api/v1/web/branches/jobs
```

**Request Body:**
```json
{
    "department_id": 1,
    "name": "Job Title",
    "code": "JOB001",
    "description": "Job description",
    "salary_range_min": 3000,
    "salary_range_max": 5000
}
```

#### Update Job
```http
PUT /api/v1/web/branches/jobs/{job_id}
```

#### Delete Job
```http
DELETE /api/v1/web/branches/jobs/{job_id}
```

### Employee Management

#### Get My Employees
```http
GET /api/v1/web/branches/employees/get_my_employees
```

#### Get My Branches
```http
GET /api/v1/web/branches/employees/get_my_branches
```

#### Get My Branch Departments
```http
GET /api/v1/web/branches/employees/get_my_branches_department
```

#### Get My Branch Jobs
```http
GET /api/v1/web/branches/employees/get_my_branches_job/{department_id}
```

#### Get Countries
```http
GET /api/v1/web/branches/employees/get_countries
```

#### Get Cities by Country
```http
GET /api/v1/web/branches/employees/get_cities_by_country/{country_id}
```

#### Get My Role for Branch
```http
GET /api/v1/web/branches/employees/get_my_role_branch
```

#### Create My Employee
```http
POST /api/v1/web/branches/employees/create_my_employee
```

**Request Body:**
```json
{
    "full_name": "Employee Full Name",
    "phone": "1234567890",
    "department_id": 1,
    "country_id": 1,
    "city_id": 1,
    "national_id": "123456789",
    "branch_job_id": 1,
    "role_id": 1,
    "username": "employee_username",
    "email": "employee@example.com",
    "mobile": "1234567890",
    "salary": 5000.00,
    "hire_date": "2024-01-01",
    "commission": 500.00,
    "contract_end_date": "2024-12-31",
    "fingerprint_device_number": "DEV001",
    "work_from": "09:00",
    "work_to": "17:00"
}
```

#### Update My Employee
```http
PUT /api/v1/web/branches/employees/update_my_employees/{employee_uuid}
```

#### Delete My Employee
```http
DELETE /api/v1/web/branches/employees/delete_my_employee/{employee_uuid}
```

#### Get Deleted My Employees
```http
GET /api/v1/web/branches/employees/get_deleted_my_employees
```

#### Restore My Employee
```http
GET /api/v1/web/branches/employees/restore_my_employees/{employee_uuid}
```

#### Force Delete My Employee
```http
DELETE /api/v1/web/branches/employees/force_delete_my_employees/{employee_uuid}
```

#### Block My Employee
```http
POST /api/v1/web/branches/employees/block_my_employees/{employee_uuid}
```

### Category Management

#### Get Categories
```http
GET /api/v1/web/branches/categories
```

#### Create Category
```http
POST /api/v1/web/branches/categories
```

**Request Body:**
```json
{
    "name": "Category Name",
    "description": "Category description"
}
```

#### Update Category
```http
PUT /api/v1/web/branches/categories/{category_id}
```

#### Delete Category
```http
DELETE /api/v1/web/branches/categories/{category_id}
```

### Sub Category Management

#### Get Sub Categories
```http
GET /api/v1/web/branches/sub_categories
```

#### Get My Categories
```http
GET /api/v1/web/branches/sub_categories/get_my_categories
```

#### Create Sub Category
```http
POST /api/v1/web/branches/sub_categories
```

**Request Body:**
```json
{
    "category_id": 1,
    "name": "Sub Category Name",
    "description": "Sub category description"
}
```

#### Update Sub Category
```http
PUT /api/v1/web/branches/sub_categories/{sub_category_id}
```

#### Delete Sub Category
```http
DELETE /api/v1/web/branches/sub_categories/{sub_category_id}
```

### Inventory Management

#### Get Inventories
```http
GET /api/v1/web/branches/inventories
```

#### Get Categories for Inventory
```http
GET /api/v1/web/branches/inventories/ge_category
```

#### Get Sub Categories by Category
```http
GET /api/v1/web/branches/inventories/get_sub_category_by_categories/{category_id}
```

#### Create Inventory
```http
POST /api/v1/web/branches/inventories
```

**Request Body:**
```json
{
    "name": "Inventory Item Name",
    "code": "INV001",
    "category_id": 1,
    "subCategories_id": 1,
    "price": 100.00,
    "type": "raw|product",
    "notes": "Inventory notes",
    "quantity": 50
}
```

#### Update Inventory
```http
PUT /api/v1/web/branches/inventories/{inventory_id}
```

#### Delete Inventory
```http
DELETE /api/v1/web/branches/inventories/{inventory_id}
```

### Inventory Transfer Management

#### Get Inventory Transfers
```http
GET /api/v1/web/branches/inventory_transfers
```

#### Create Inventory Transfer
```http
POST /api/v1/web/branches/inventory_transfers
```

**Request Body:**
```json
{
    "to_branch_id": 2,
    "inventory_id": 1,
    "quantity": 10,
    "notes": "Transfer notes"
}
```

#### Approve Transfer
```http
POST /api/v1/web/branches/inventory_transfers/{transfer_id}/approve
```

#### Reject Transfer
```http
POST /api/v1/web/branches/inventory_transfers/{transfer_id}/reject
```

## Employee Endpoints

All employee endpoints require authentication with `employee-api` guard.

### Department Management

#### Get Departments
```http
GET /api/v1/web/employees/departments
```

#### Create Department
```http
POST /api/v1/web/employees/departments
```

#### Update Department
```http
PUT /api/v1/web/employees/departments/{department_id}
```

#### Delete Department
```http
DELETE /api/v1/web/employees/departments/{department_id}
```

### Job Management

#### Get Departments for Job
```http
GET /api/v1/web/employees/jobs/get_department
```

#### Get Jobs
```http
GET /api/v1/web/employees/jobs
```

#### Create Job
```http
POST /api/v1/web/employees/jobs
```

#### Update Job
```http
PUT /api/v1/web/employees/jobs/{job_id}
```

#### Delete Job
```http
DELETE /api/v1/web/employees/jobs/{job_id}
```

### Category Management

#### Get Categories
```http
GET /api/v1/web/employees/categories
```

#### Create Category
```http
POST /api/v1/web/employees/categories
```

#### Update Category
```http
PUT /api/v1/web/employees/categories/{category_id}
```

#### Delete Category
```http
DELETE /api/v1/web/employees/categories/{category_id}
```

### Sub Category Management

#### Get Sub Categories
```http
GET /api/v1/web/employees/sub_categories
```

#### Create Sub Category
```http
POST /api/v1/web/employees/sub_categories
```

#### Update Sub Category
```http
PUT /api/v1/web/employees/sub_categories/{sub_category_id}
```

#### Delete Sub Category
```http
DELETE /api/v1/web/employees/sub_categories/{sub_category_id}
```

### Inventory Management

#### Get Inventories
```http
GET /api/v1/web/employees/inventories
```

#### Get Categories for Inventory
```http
GET /api/v1/web/employees/inventories/ge_category
```

#### Get Sub Categories by Category
```http
GET /api/v1/web/employees/inventories/get_sub_category_by_categories/{category_id}
```

#### Create Inventory
```http
POST /api/v1/web/employees/inventories
```

#### Update Inventory
```http
PUT /api/v1/web/employees/inventories/{inventory_id}
```

#### Delete Inventory
```http
DELETE /api/v1/web/employees/inventories/{inventory_id}
```

### Inventory Transfer Management

#### Get Inventory Transfers
```http
GET /api/v1/web/employees/inventory_transfers
```

#### Create Inventory Transfer
```http
POST /api/v1/web/employees/inventory_transfers
```

#### Approve Transfer
```http
POST /api/v1/web/employees/inventory_transfers/{transfer_uuid}/approve
```

#### Reject Transfer
```http
POST /api/v1/web/employees/inventory_transfers/{transfer_uuid}/reject
```

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
    "status": true,
    "message": "Success message in Arabic",
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "status": false,
    "message": "Error message in Arabic"
}
```

### Pagination Response
```json
{
    "status": true,
    "message": "Success message",
    "data": {
        "current_page": 1,
        "data": [
            // Array of items
        ],
        "first_page_url": "url",
        "from": 1,
        "last_page": 10,
        "last_page_url": "url",
        "links": [
            // Pagination links
        ],
        "next_page_url": "url",
        "path": "url",
        "per_page": 10,
        "prev_page_url": null,
        "to": 10,
        "total": 100
    }
}
```

## Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

## Common Validation Rules

### File Upload
- **Image files**: `image|mimes:jpeg,png,jpg,gif,webp|max:2048`
- **Required fields**: Must be provided and not empty
- **Unique fields**: Must be unique across the system
- **Email format**: Must be valid email format
- **Phone format**: Must be valid phone number format
- **Date format**: Must be valid date format (YYYY-MM-DD)
- **Time format**: Must be valid time format (HH:MM)

### Authentication Requirements
- All protected endpoints require valid Bearer token
- Token must be associated with the correct user type
- User must have appropriate permissions for the requested action

## Rate Limiting

The API implements rate limiting to prevent abuse:
- **Login attempts**: 5 attempts per minute per IP
- **API calls**: 1000 requests per hour per user
- **File uploads**: 10 uploads per hour per user

## Security Features

1. **Authentication**: Laravel Passport OAuth2 implementation
2. **Authorization**: Role-based access control (RBAC)
3. **Input Validation**: Comprehensive validation for all inputs
4. **SQL Injection Protection**: Eloquent ORM with parameterized queries
5. **XSS Protection**: Input sanitization and output escaping
6. **CSRF Protection**: Token-based CSRF protection
7. **Rate Limiting**: API rate limiting to prevent abuse
8. **File Upload Security**: File type and size validation

## Support

For technical support or questions about the API, please contact the development team.

---

**Last Updated**: December 2024
**API Version**: v1
**Framework**: Laravel 12
**Authentication**: Laravel Passport
