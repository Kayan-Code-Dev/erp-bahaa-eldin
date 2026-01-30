# Test Coverage Suite

This directory contains comprehensive test coverage for the Atelier Management System, based on all scenarios documented in `docs/TEST_COVERAGE.md`.

## Overview

This test suite provides comprehensive coverage of:
- All CRUD operations
- Permission and role-based access control
- Business logic and workflows
- Integration scenarios
- Edge cases and error handling

## Test Organization

Tests are organized by module:

```
tests/Coverage/
├── Authentication/       # Login, logout, permissions, middleware
├── CoreEntities/         # Countries, Cities, Addresses, Categories, etc.
├── Clients/              # Client management, measurements, phones
├── Orders/               # Order lifecycle, status transitions
├── Payments/             # Payment processing and status
├── Custody/              # Custody management
├── Rental/               # Rental and appointments
├── Transfers/            # Transfer workflows
├── Workshops/            # Workshop management
├── Factory/              # Factory operations
├── Tailoring/            # Tailoring stages
├── Accounting/           # Cashbox, transactions, expenses, receivables
├── HR/                   # Employees, attendance, payroll, documents
├── Reports/              # All report types
├── Notifications/        # Notification system
├── Users/                # User and role management
├── Dashboard/            # Dashboard metrics
└── Integration/          # Multi-module workflows
```

## Test Structure

Each test file follows a consistent structure:

1. **Setup**: Uses `RefreshDatabase` trait, creates permissions, roles, and test data
2. **Helper Methods**: Common methods for creating users with permissions
3. **Test Methods**: Cover CRUD operations, permissions, validations, edge cases
4. **Assertions**: Verify status codes, database state, response structure

## Running Tests

Run all coverage tests:
```bash
php artisan test tests/Coverage
```

Run tests for a specific module:
```bash
php artisan test tests/Coverage/Authentication
php artisan test tests/Coverage/CoreEntities
```

Run a specific test file:
```bash
php artisan test tests/Coverage/Authentication/AuthenticationTest.php
```

## Test Coverage Status

Based on `docs/TEST_COVERAGE.md` (207 test scenarios):

- ✅ Authentication & Authorization Module
- ✅ Core Entities (Countries, Cities, etc.)
- ⏳ Client Management Module
- ⏳ Order Management Module
- ⏳ Payment Module
- ⏳ Custody Module
- ⏳ Rental/Appointments Module
- ⏳ Transfer Module
- ⏳ Workshop Module
- ⏳ Factory Module
- ⏳ Tailoring Module
- ⏳ Accounting Module
- ⏳ HR Module
- ⏳ Reports Module
- ⏳ Notifications Module
- ⏳ User & Role Management Module
- ⏳ Dashboard Module
- ⏳ Integration Tests

## Test Principles

All tests follow these principles:

1. **Isolation**: Each test is independent (uses `RefreshDatabase`)
2. **Permissions**: Tests verify both allowed and denied access
3. **Validation**: Tests include validation error scenarios
4. **Business Logic**: Tests cover status transitions and workflows
5. **Super Admin**: Tests verify super admin bypasses permissions
6. **Edge Cases**: Tests cover error conditions and boundary cases

## Permission Testing Pattern

Each endpoint test includes:
- ✅ User with required permission (should pass)
- ❌ User without permission (should fail with 403)
- ❌ Unauthenticated user (should fail with 401)
- ✅ Super admin (should always pass)

## Notes

- Tests use Laravel Sanctum for authentication
- Tests use factories for test data generation
- All tests follow PHPUnit conventions
- Test method names use `test_` prefix or `@test` annotation



