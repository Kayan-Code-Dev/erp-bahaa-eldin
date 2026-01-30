# Frontend API Guide

## Table of Contents

1. [Introduction](#introduction)
2. [Authentication](#authentication)
3. [Base URL](#base-url)
4. [Common Patterns](#common-patterns)
5. [Entity Endpoints](#entity-endpoints)
6. [Transfer Workflow](#transfer-workflow)
7. [Error Handling](#error-handling)
8. [Code Examples](#code-examples)

## Introduction

This guide provides comprehensive documentation for frontend developers integrating with the Bahaa-Eldin Inventory Management System API. The API follows RESTful conventions and uses JSON for data exchange.

### API Version

All endpoints are versioned under `/api/v1/`

### Response Format

All successful responses return JSON. Paginated responses include:
- `data`: Array of items
- `current_page`: Current page number
- `per_page`: Items per page
- `total`: Total items
- `total_pages`: Total number of pages
- `last_page`: Last page number

## Authentication

### Getting a Token

**Endpoint:** `POST /api/v1/login`

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  },
  "token": "1|abcdef123456..."
}
```

### Using the Token

Include the token in the `Authorization` header for all protected endpoints:

```
Authorization: Bearer 1|abcdef123456...
```

### Logout

**Endpoint:** `POST /api/v1/logout`

**Headers:**
```
Authorization: Bearer {token}
```

## Base URL

```
https://your-domain.com/api/v1
```

For local development:
```
http://localhost:8000/api/v1
```

## Common Patterns

### Pagination

Most list endpoints support pagination:

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15)

**Example:**
```
GET /api/v1/branches?page=2&per_page=20
```

### Filtering

Some endpoints support filtering via query parameters:

**Example:**
```
GET /api/v1/transfers?status=pending
```

### Including Relationships

Relationships are automatically included in responses. For example, when fetching a branch, you'll receive:
- `inventory`: The branch's inventory
- `address`: The branch's address

## Entity Endpoints

### Branches

#### List Branches
```
GET /api/v1/branches
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "branch_code": "BR-001",
      "name": "Downtown Branch",
      "address_id": 1,
      "inventory": {
        "id": 1,
        "name": "Downtown Branch Inventory",
        "inventoriable_type": "App\\Models\\Branch",
        "inventoriable_id": 1
      },
      "address": {
        "id": 1,
        "street": "123 Main St",
        "city_id": 1
      }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 1,
  "total_pages": 1
}
```

#### Get Single Branch
```
GET /api/v1/branches/{id}
```

#### Create Branch
```
POST /api/v1/branches
```

**Request:**
```json
{
  "branch_code": "BR-002",
  "name": "Uptown Branch",
  "address": {
    "street": "Tahrir Square",
    "building": "2A",
    "city_id": 1,
    "notes": "Next to the bank, 3rd floor"
  },
  "inventory_name": "Uptown Branch Inventory" // Optional
}
```

**Note:** 
- An inventory is automatically created when a branch is created
- An address is automatically created from the provided address details
- Address fields: `street` (required), `building` (required), `city_id` (required), `notes` (optional)

#### Update Branch
```
PUT /api/v1/branches/{id}
```

**Request:**
```json
{
  "name": "Updated Branch Name",
  "address_id": 3
}
```

#### Delete Branch
```
DELETE /api/v1/branches/{id}
```

### Workshops

Workshops follow the same pattern as branches:

- `GET /api/v1/workshops` - List workshops
- `GET /api/v1/workshops/{id}` - Get single workshop
- `POST /api/v1/workshops` - Create workshop
- `PUT /api/v1/workshops/{id}` - Update workshop
- `DELETE /api/v1/workshops/{id}` - Delete workshop

**Create Request:**
```json
{
  "workshop_code": "WS-001",
  "name": "Main Workshop",
  "address": {
    "street": "Industrial Zone",
    "building": "Block 5",
    "city_id": 1,
    "notes": "Near main entrance"
  },
  "inventory_name": "Main Workshop Inventory" // Optional
}
```

**Note:** Address is automatically created from the provided address details.

### Factories

Factories follow the same pattern as branches:

- `GET /api/v1/factories` - List factories
- `GET /api/v1/factories/{id}` - Get single factory
- `POST /api/v1/factories` - Create factory
- `PUT /api/v1/factories/{id}` - Update factory
- `DELETE /api/v1/factories/{id}` - Delete factory

**Create Request:**
```json
{
  "factory_code": "FA-001",
  "name": "Main Factory",
  "address": {
    "street": "Factory District",
    "building": "Building 10",
    "city_id": 1,
    "notes": "Large warehouse facility"
  },
  "inventory_name": "Main Factory Inventory" // Optional
}
```

**Note:** Address is automatically created from the provided address details.

### Inventories

#### List Inventories
```
GET /api/v1/inventories
```

#### Get Single Inventory
```
GET /api/v1/inventories/{id}
```

#### Get Inventory Clothes
```
GET /api/v1/inventories/{id}/clothes
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "CL-001",
      "name": "Red Dress",
      "pivot": {
        "quantity": 10,
        "available_quantity": 8
      }
    }
  ],
  "current_page": 1,
  "per_page": 15,
  "total": 1
}
```

#### Create Inventory
```
POST /api/v1/inventories
```

**Note:** Inventories are usually created automatically. Manual creation requires:
```json
{
  "name": "Inventory Name",
  "inventoriable_type": "App\\Models\\Branch",
  "inventoriable_id": 1
}
```

#### Update Inventory
```
PUT /api/v1/inventories/{id}
```

**Request:**
```json
{
  "name": "Updated Inventory Name"
}
```

#### Delete Inventory
```
DELETE /api/v1/inventories/{id}
```

### Clothes

#### List Clothes
```
GET /api/v1/clothes
```

#### Get Single Cloth
```
GET /api/v1/clothes/{id}
```

#### Create Cloth
```
POST /api/v1/clothes
```

**Request:**
```json
{
  "code": "CL-001",
  "name": "Red Evening Dress",
  "description": "Beautiful red dress for evening events",
  "breast_size": "38",
  "waist_size": "32",
  "sleeve_size": "34",
  "status": "ready_for_rent",
  "notes": "Handle with care"
}
```

**Status Values:**
- `damaged` - Cloth is damaged
- `burned` - Cloth is burned
- `scratched` - Cloth is scratched
- `ready_for_rent` - Cloth is ready for rent (default)
- `rented` - Cloth is currently rented out
- `die` - Cloth is no longer usable

#### Update Cloth
```
PUT /api/v1/clothes/{id}
```

#### Delete Cloth
```
DELETE /api/v1/clothes/{id}
```

### Orders

#### List Orders
```
GET /api/v1/orders
```

#### Get Single Order
```
GET /api/v1/orders/{id}
```

#### Create Order
```
POST /api/v1/orders
```

**Request:**
```json
{
  "client_id": 1,
  "inventory_id": 1,
  "address_id": 1,
  "total_price": 100.50,
  "status": "pending",
  "delivery_date": "2025-12-25",
  "clothes": [
    {
      "cloth_id": 1,
      "price": 50.00,
      "days_of_rent": 3,
      "paid": 20.00,
      "remaining": 30.00,
      "visit_datetime": "2025-12-20 10:00:00",
      "occasion_datetime": "2025-12-25 18:00:00",
      "from_where_you_know_us": "Social Media",
      "status": "rented"
    }
  ]
}
```

**Note:** `delivery_date` is the date when the order should be delivered to the client.

#### Update Order
```
PUT /api/v1/orders/{id}
```

**Request:**
```json
{
  "total_price": 120.00,
  "status": "completed",
  "delivery_date": "2025-12-26"
}
```

#### Delete Order
```
DELETE /api/v1/orders/{id}
```

## Transfer Workflow

### Creating a Transfer

**Endpoint:** `POST /api/v1/transfers`

**Request:**
```json
{
  "from_entity_type": "App\\Models\\Branch",
  "from_entity_id": 1,
  "to_entity_type": "App\\Models\\Workshop",
  "to_entity_id": 2,
  "cloth_id": 1,
  "quantity": 5,
  "transfer_date": "2025-12-20",
  "notes": "Urgent transfer needed"
}
```

**Response:**
```json
{
  "id": 1,
  "from_entity_type": "App\\Models\\Branch",
  "from_entity_id": 1,
  "to_entity_type": "App\\Models\\Workshop",
  "to_entity_id": 2,
  "cloth_id": 1,
  "quantity": 5,
  "transfer_date": "2025-12-20",
  "notes": "Urgent transfer needed",
  "status": "pending",
  "fromEntity": {
    "id": 1,
    "branch_code": "BR-001",
    "name": "Downtown Branch"
  },
  "toEntity": {
    "id": 2,
    "workshop_code": "WS-001",
    "name": "Main Workshop"
  },
  "cloth": {
    "id": 1,
    "code": "CL-001",
    "name": "Red Dress"
  }
}
```

**Validation Rules:**
- Source and destination must be different
- Both entities must exist and have inventories
- Cloth must exist in source inventory
- Source must have sufficient `available_quantity`

### Listing Transfers

**Endpoint:** `GET /api/v1/transfers`

**Query Parameters:**
- `status`: Filter by status (`pending`, `approved`, `rejected`)
- `page`: Page number
- `per_page`: Items per page

**Example:**
```
GET /api/v1/transfers?status=pending&page=1&per_page=20
```

### Getting a Single Transfer

**Endpoint:** `GET /api/v1/transfers/{id}`

### Updating a Transfer

**Endpoint:** `PUT /api/v1/transfers/{id}`

**Note:** Only pending transfers can be updated.

**Request:**
```json
{
  "quantity": 10,
  "transfer_date": "2025-12-21",
  "notes": "Updated notes"
}
```

### Approving a Transfer

**Endpoint:** `POST /api/v1/transfers/{id}/approve`

**Response:**
```json
{
  "id": 1,
  "status": "approved",
  // ... other fields
}
```

**What Happens:**
1. System validates transfer is pending
2. System checks availability again
3. If valid:
   - Decreases quantity in source inventory
   - Increases quantity in destination inventory
   - Updates transfer status to "approved"

### Rejecting a Transfer

**Endpoint:** `POST /api/v1/transfers/{id}/reject`

**Response:**
```json
{
  "id": 1,
  "status": "rejected",
  // ... other fields
}
```

### Deleting a Transfer

**Endpoint:** `DELETE /api/v1/transfers/{id}`

**Note:** Only pending transfers can be deleted.

## Error Handling

### Error Response Format

All errors follow this format:

```json
{
  "message": "Error message",
  "errors": {
    "field_name": ["Error message for field"]
  }
}
```

### HTTP Status Codes

- `200 OK` - Successful GET, PUT, PATCH
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE
- `400 Bad Request` - Invalid request
- `401 Unauthorized` - Missing or invalid token
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation error
- `500 Internal Server Error` - Server error

### Common Error Scenarios

#### Validation Error (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "branch_code": ["The branch code has already been taken."],
    "address_id": ["The selected address id is invalid."]
  }
}
```

#### Not Found (404)
```json
{
  "message": "No query results for model [App\\Models\\Branch] 999"
}
```

#### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

#### Business Logic Error (422)
```json
{
  "message": "Insufficient available quantity",
  "errors": {
    "quantity": ["Available quantity is 3, requested: 5"]
  }
}
```

## Code Examples

### JavaScript/TypeScript (Fetch API)

#### Authentication
```typescript
async function login(email: string, password: string) {
  const response = await fetch('http://localhost:8000/api/v1/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    throw new Error('Login failed');
  }

  const data = await response.json();
  localStorage.setItem('token', data.token);
  return data;
}
```

#### Creating a Branch
```typescript
async function createBranch(branchData: {
  branch_code: string;
  name: string;
  address: {
    street: string;
    building: string;
    city_id: number;
    notes?: string;
  };
  inventory_name?: string;
}) {
  const token = localStorage.getItem('token');
  
  const response = await fetch('http://localhost:8000/api/v1/branches', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(branchData),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to create branch');
  }

  return await response.json();
}
```

#### Creating a Transfer
```typescript
async function createTransfer(transferData: {
  from_entity_type: string;
  from_entity_id: number;
  to_entity_type: string;
  to_entity_id: number;
  cloth_id: number;
  quantity: number;
  transfer_date: string;
  notes?: string;
}) {
  const token = localStorage.getItem('token');
  
  const response = await fetch('http://localhost:8000/api/v1/transfers', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(transferData),
  });

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to create transfer');
  }

  return await response.json();
}
```

#### Approving a Transfer
```typescript
async function approveTransfer(transferId: number) {
  const token = localStorage.getItem('token');
  
  const response = await fetch(
    `http://localhost:8000/api/v1/transfers/${transferId}/approve`,
    {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    }
  );

  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || 'Failed to approve transfer');
  }

  return await response.json();
}
```

#### Listing with Pagination
```typescript
async function listBranches(page: number = 1, perPage: number = 15) {
  const token = localStorage.getItem('token');
  
  const response = await fetch(
    `http://localhost:8000/api/v1/branches?page=${page}&per_page=${perPage}`,
    {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`,
      },
    }
  );

  if (!response.ok) {
    throw new Error('Failed to fetch branches');
  }

  return await response.json();
}
```

### Axios Example

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Create branch
async function createBranch(data: BranchData) {
  const response = await api.post('/branches', data);
  return response.data;
}

// List branches with pagination
async function listBranches(page = 1, perPage = 15) {
  const response = await api.get('/branches', {
    params: { page, per_page: perPage },
  });
  return response.data;
}

// Create transfer
async function createTransfer(data: TransferData) {
  const response = await api.post('/transfers', data);
  return response.data;
}

// Approve transfer
async function approveTransfer(id: number) {
  const response = await api.post(`/transfers/${id}/approve`);
  return response.data;
}
```

## Common Workflows

### Workflow 1: Creating a Branch with Inventory

1. Create branch with address details (address and inventory are automatically created)
2. Add clothes to inventory (via cloth_inventory pivot)

```typescript
// Step 1: Create branch with address details
const branch = await createBranch({
  branch_code: 'BR-001',
  name: 'Downtown Branch',
  address: {
    street: 'Tahrir Square',
    building: '2A',
    city_id: 1,
    notes: 'Next to the bank'
  }
});

// Branch now has an inventory and address automatically created
console.log(branch.inventory.id); // Inventory ID
console.log(branch.address.id); // Address ID
```

### Workflow 2: Transferring Clothes

1. Check available quantity in source inventory
2. Create transfer request
3. Approve transfer (updates inventories)

```typescript
// Step 1: Get source inventory clothes
const sourceInventory = await api.get(`/inventories/${sourceInventoryId}/clothes`);
const cloth = sourceInventory.data.find(c => c.id === clothId);

// Step 2: Check availability
if (cloth.pivot.available_quantity < quantity) {
  throw new Error('Insufficient quantity');
}

// Step 3: Create transfer
const transfer = await createTransfer({
  from_entity_type: 'App\\Models\\Branch',
  from_entity_id: sourceBranchId,
  to_entity_type: 'App\\Models\\Workshop',
  to_entity_id: destinationWorkshopId,
  cloth_id: clothId,
  quantity: quantity,
  transfer_date: new Date().toISOString().split('T')[0],
  notes: 'Transfer notes',
});

// Step 4: Approve transfer
await approveTransfer(transfer.id);
```

### Workflow 3: Managing Inventory Contents

To add clothes to an inventory, you'll need to work with the `cloth_inventory` pivot table. This is typically done through the backend, but you can:

1. Get inventory clothes: `GET /inventories/{id}/clothes`
2. Check quantities via the `pivot` object in the response

## Best Practices

1. **Always handle errors**: Check response status before processing data
2. **Store tokens securely**: Use secure storage (not localStorage for production)
3. **Implement retry logic**: For network failures
4. **Cache when appropriate**: Cache entity lists that don't change frequently
5. **Validate on frontend**: But always trust backend validation
6. **Handle pagination**: Implement infinite scroll or pagination UI
7. **Show loading states**: Provide user feedback during API calls
8. **Handle token expiration**: Implement token refresh or re-login flow

## Swagger Documentation

Interactive API documentation is available at:
```
http://localhost:8000/api/docs
```

This provides:
- Interactive API testing
- Request/response examples
- Schema definitions
- Authentication testing

## Support

For API issues or questions, refer to:
- System Architecture Documentation: `docs/SYSTEM_ARCHITECTURE.md`
- Swagger UI: `/api/docs`
- Backend team for business logic questions

