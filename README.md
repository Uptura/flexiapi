# 🚀 FlexiAPI Framework v3.5.0

[![Latest Version](https://img.shields.io/packagist/v/uptura-official/flexiapi.svg)](https://packagist.org/packages/uptura-official/flexiapi)
[![PHP Version](https://img.shields.io/packagist/php-v/uptura-official/flexiapi.svg)](https://packagist.org/packages/uptura-official/flexiapi)
[![License](https://img.shields.io/packagist/l/uptura-official/flexiapi.svg)](https://github.com/Uptura/flexiapi/blob/main/LICENSE)

**FlexiAPI** is a powerful, zero-configuration CLI framework for rapid REST API development. Build production-ready APIs with authentication, encryption, pagination, and CORS in minutes, not hours.

## 🌟 Key Features

- 🎯 **Zero Configuration** - Get started immediately without complex setup
- 🔐 **Built-in Authentication** - JWT with custom headers (Auth-x)
- 🛡️ **Field-Level Encryption** - AES-256-CBC encryption for sensitive data
- 📊 **Advanced Querying** - Pagination, search, filtering, and sorting
- 🌐 **Dynamic CORS** - CLI-configurable CORS policies
- ⚡ **Rapid Development** - Create full CRUD APIs in seconds
- 🔧 **Flexible CLI** - Works in development and production environments
- 📦 **Easy Deployment** - Composer-ready package management

## 📦 Installation

### Global Installation
```bash
composer global require uptura-official/flexiapi
flexiapi setup
```

### Stable Version (Recommended)
```bash
composer require uptura-official/flexiapi:^3.4
flexiapi setup
```

### Local Installation
```bash
composer require uptura-official/flexiapi
vendor/bin/flexiapi setup
```

### Development Installation
```bash
git clone https://github.com/Uptura/flexiapi.git
cd flexiapi
composer install
php bin/flexiapi setup
```

## 🚀 Quick Start

### 1. Initialize Your API
```bash
flexiapi setup
# Configures database, authentication, and basic settings
```

### 2. Create Your First Endpoint
```bash
flexiapi create:endpoint users
# Interactive setup: define columns, data types, encryption
```

### 3.0 Start Development Server
```bash
flexiapi serve
# Launches PHP development server with your API
```

### 3.1 PHP Server
```bash
php -S 127.0.0.1:8000 -t public
# Launches PHP development server with your API
```

### 4. Test Your API
Your API is immediately available with full CRUD operations:

```bash
# List all users (with pagination)
curl -H "Auth-x: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/users

# Create a new user
curl -X POST -H "Auth-x: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com"}' \
  http://localhost:8000/api/v1/users

# Get specific user
curl -H "Auth-x: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/users/1

# Update user
curl -X PUT -H "Auth-x: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Doe"}' \
  http://localhost:8000/api/v1/users/1

# Delete user
curl -X DELETE -H "Auth-x: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/users/1
```

## 🔐 Authentication

FlexiAPI uses JWT tokens with a custom `Auth-x` header for enhanced security.

### Generate API Keys
```bash
curl -X POST http://localhost:8000/api/v1/auth/generate_keys
```

**Response:**
```json
{
  "success": true,
  "message": "API keys generated successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_at": "2025-10-11 22:00:00"
  }
}
```

### Use Authentication
Include the `Auth-x` header in all authenticated requests:
```bash
curl -H "Auth-x: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/users
```

## 🛡️ Field-Level Encryption

Protect sensitive data with built-in AES-256-CBC encryption.

### Configure Encryption
```bash
flexiapi update:endpoint users
# Choose option to configure field encryption
```

### Automatic Encryption/Decryption
```bash
# Create user with encrypted field
curl -X POST -H "Auth-x: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"John","ssn":"123-45-6789"}' \
  http://localhost:8000/api/v1/users

# SSN is automatically encrypted in database
# SSN is automatically decrypted in API responses
```

## 📊 Advanced Querying

### Pagination
```bash
# Get page 2 with 10 items per page
curl -H "Auth-x: Bearer TOKEN" \
  "http://localhost:8000/api/v1/users?page=2&limit=10"
```

### Search Across All Fields
```bash
# Search for "john" across all searchable fields
curl -H "Auth-x: Bearer TOKEN" \
  "http://localhost:8000/api/v1/users?search=john"
```

### Column-Specific Search
```bash
# Search by specific column
curl -H "Auth-x: Bearer TOKEN" \
  "http://localhost:8000/api/v1/users/search/email?q=gmail.com"
```

### Sorting
```bash
# Sort by name ascending
curl -H "Auth-x: Bearer TOKEN" \
  "http://localhost:8000/api/v1/users?sort=name&order=ASC"
```

### Combined Queries
```bash
# Complex query: search + pagination + sorting
curl -H "Auth-x: Bearer TOKEN" \
  "http://localhost:8000/api/v1/users?search=john&page=1&limit=5&sort=created_at&order=DESC"
```

### Response Format
```json
{
  "success": true,
  "message": "Records retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "created_at": "2025-10-10 12:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 25,
    "pages": 3
  }
}
```

## 🌐 CORS Configuration

Configure CORS policies dynamically via CLI.

### Interactive CORS Setup
```bash
flexiapi configure:cors
# Guides you through origins, methods, headers, and credentials
```

### Example CORS Configuration
```bash
Origins: https://yourdomain.com, https://app.yourdomain.com
Methods: GET, POST, PUT, DELETE, OPTIONS
Headers: Content-Type, Authorization, Auth-x
Credentials: true
Max Age: 86400 seconds
```

## 🔧 CLI Commands

### Core Commands
```bash
flexiapi setup                    # Initial framework configuration
flexiapi create:endpoint <name>   # Create new API endpoint
flexiapi update:endpoint <name>   # Modify existing endpoint
flexiapi list:endpoints           # Show all endpoints
flexiapi configure:cors           # Configure CORS policy
flexiapi serve [--port=8000]      # Start development server
```

### Generation Commands
```bash
flexiapi generate:postman         # Generate Postman collection
flexiapi export:sql               # Export unified SQL schema
```

### Aliases
```bash
flexiapi create users    # Same as create:endpoint users
flexiapi update users    # Same as update:endpoint users
flexiapi list           # Same as list:endpoints
flexiapi cors           # Same as configure:cors
flexiapi serve          # Start development server
```

## 📁 Project Structure

```
your-api/
├── config/
│   ├── config.php          # Database & app configuration
│   └── cors.php            # CORS policy settings
├── endpoints/
│   ├── UsersController.php # Generated endpoint controllers
│   └── usersRoutes.php     # Generated route definitions
├── sql/
│   ├── users.sql           # Individual table schemas
│   └── products.sql
├── exports/
│   └── FlexiAPI_Schema_Latest.sql  # Unified schema export
├── public/
│   └── index.php           # API entry point
└── storage/
    ├── logs/               # Application logs
    └── cache/              # Rate limiting cache
```

## 🔄 API Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    "id": 1,
    "name": "John Doe"
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": "Email is required"
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "message": "Records retrieved successfully",
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 100,
    "pages": 10
  }
}
```

## 🛠️ Development Workflow

### 1. Create New Feature Endpoint
```bash
flexiapi create:endpoint products
# Define columns: name, price, description
# Configure encryption for sensitive pricing data
```

### 2. Update Existing Endpoint
```bash
flexiapi update:endpoint products
# Add columns, modify types, configure encryption
```

### 3. Test API Endpoints
```bash
flexiapi generate:postman
# Creates ready-to-use Postman collection
```

### 4. Export Database Schema
```bash
flexiapi export:sql
# Creates unified SQL file for deployment
```

## 🚀 Deployment

### Production Setup
1. Install on production server:
```bash
composer global require uptura-official/flexiapi
```

2. Configure production database:
```bash
flexiapi setup
# Enter production database credentials
```

3. Deploy your endpoint files:
```bash
# Copy your development files to production
cp -r endpoints/ /var/www/your-api/
cp -r sql/ /var/www/your-api/
cp config/cors.php /var/www/your-api/config/
```

4. Import database schema:
```bash
mysql -u user -p database < exports/FlexiAPI_Schema_Latest.sql
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup
```bash
git clone https://github.com/Uptura/flexiapi.git
cd flexiapi
composer install
php bin/flexiapi setup
```

## 📄 License

FlexiAPI is open-sourced software licensed under the [MIT license](LICENSE).

## 🔗 Links

- 📖 [Documentation](https://github.com/Uptura/flexiapi#readme)
- 🐛 [Issues](https://github.com/Uptura/flexiapi/issues)
- 💬 [Discussions](https://github.com/Uptura/flexiapi/discussions)
- 🌟 [Changelog](CHANGELOG.md)

## 💡 Support

- **Documentation**: Full guides and examples in this README
- **Issues**: Report bugs on [GitHub Issues](https://github.com/Uptura/flexiapi/issues)
- **Community**: Join discussions on [GitHub Discussions](https://github.com/Uptura/flexiapi/discussions)

---

**Made with ❤️ by [Uptura](https://uptura-tech.com)**
- ✅ Postman collection generation
- ✅ SQL export functionality