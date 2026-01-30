# ERP Bhaa Backend API Documentation

## Quick Start Guide

This repository contains comprehensive API documentation and Postman collection for the ERP Bhaa Backend system.

### Files Included

1. **API_DOCUMENTATION.md** - Complete API documentation with all endpoints
2. **ERP_Bhaa_API_Collection.postman_collection.json** - Postman collection for testing
3. **API_README.md** - This quick start guide

### Getting Started

#### 1. Import Postman Collection

1. Open Postman
2. Click "Import" button
3. Select the `ERP_Bhaa_API_Collection.postman_collection.json` file
4. The collection will be imported with all endpoints organized by user type

#### 2. Set Up Environment Variables

Create a new environment in Postman with these variables:

```
base_url: http://your-domain.com/api/v1/web
access_token: (will be set after login)
branch_manager_token: (will be set after login)
branch_token: (will be set after login)
employee_token: (will be set after login)
```

#### 3. Authentication Flow

1. **Login**: Use the Authentication > Login endpoint
2. **Copy Token**: Copy the `access_token` from the response
3. **Set Token**: Update the appropriate token variable in your environment
4. **Test Endpoints**: Use the authenticated endpoints

### User Types & Access Levels

#### Admin (admin-api)
- Full system access
- Manage all users, branches, countries, cities
- Assign roles and permissions
- Manage system-wide settings

#### Branch Manager (branchManager-api)
- Manage multiple branches
- Create and manage employees
- Handle inventory transfers
- Manage branch operations

#### Branch (branch-api)
- Manage single branch operations
- Create departments, jobs, employees
- Manage inventory and categories
- Handle branch-specific tasks

#### Employee (employee-api)
- Limited access based on permissions
- Manage inventory
- Perform assigned tasks

### Key Features

#### 🔐 Multi-Guard Authentication
- Separate authentication for each user type
- Role-based access control (RBAC)
- Permission-based endpoint access

#### 📊 Comprehensive Management
- **User Management**: Admins, Branch Managers, Branches, Employees
- **Inventory Management**: Categories, Sub-categories, Items, Transfers
- **Branch Operations**: Departments, Jobs, Employee Management
- **Geographic Data**: Countries, Cities management

#### 🛡️ Security Features
- Laravel Passport OAuth2 authentication
- Input validation and sanitization
- Rate limiting
- File upload security
- SQL injection protection

### API Response Format

All responses follow a consistent format:

#### Success Response
```json
{
    "status": true,
    "message": "Success message in Arabic",
    "data": {
        // Response data
    }
}
```

#### Error Response
```json
{
    "status": false,
    "message": "Error message in Arabic"
}
```

### Common HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

### Testing the API

#### 1. Authentication Testing
```bash
# Login as Admin
POST /api/v1/web/auth/login
{
    "guard": "admin-api",
    "emOrMb": "admin@example.com",
    "password": "password123"
}
```

#### 2. Protected Endpoint Testing
```bash
# Get all admins (requires admin token)
GET /api/v1/web/admins/admins
Authorization: Bearer {admin_token}
```

#### 3. File Upload Testing
```bash
# Create admin with image upload
POST /api/v1/web/admins/admins
Content-Type: multipart/form-data
Authorization: Bearer {admin_token}

Form Data:
- role_id: 1
- first_name: Admin
- last_name: User
- email: admin@example.com
- phone: 1234567890
- id_number: 123456789
- country_id: 1
- city_id: 1
- image: [file]
```

### Environment Setup

#### Development
```bash
# Clone the repository
git clone <repository-url>

# Install dependencies
composer install

# Set up environment
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Install Passport
php artisan passport:install

# Seed initial data
php artisan db:seed
```

#### Production
```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set up Passport keys
php artisan passport:keys
```

### Rate Limiting

The API implements rate limiting:
- **Login attempts**: 5 attempts per minute per IP
- **API calls**: 1000 requests per hour per user
- **File uploads**: 10 uploads per hour per user

### Security Best Practices

1. **Always use HTTPS** in production
2. **Validate all inputs** on the client side
3. **Store tokens securely** and refresh them regularly
4. **Implement proper error handling**
5. **Use environment variables** for sensitive data
6. **Regular security updates** and monitoring

### Support & Documentation

- **API Documentation**: See `API_DOCUMENTATION.md` for complete endpoint details
- **Postman Collection**: Import `ERP_Bhaa_API_Collection.postman_collection.json`
- **Laravel Documentation**: https://laravel.com/docs
- **Passport Documentation**: https://laravel.com/docs/passport

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### License

This project is licensed under the MIT License.

---

**Last Updated**: December 2024  
**API Version**: v1  
**Framework**: Laravel 12  
**Authentication**: Laravel Passport
