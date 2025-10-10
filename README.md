# FlexiAPI Framework v2.0.0

🚀 **The Complete Rapid API Development Framework**

FlexiAPI is a powerful CLI-based framework that allows you to create full-featured REST APIs in minutes. Build endpoints, generate documentation, and deploy with zero configuration.

## ✨ Features

- **🎯 Zero-Configuration Setup** - Get started in seconds
- **⚡ Rapid Endpoint Creation** - Create full CRUD APIs with one command
- **🔄 Interactive Updates** - Modify endpoints with guided prompts
- **📚 Auto-Documentation** - Generate Postman collections automatically
- **🖥️ Built-in Dev Server** - Integrated development server with logging
- **🛡️ Security Built-in** - Rate limiting, authentication, input validation
- **📊 Smart Analytics** - Endpoint monitoring and performance insights
- **🔧 Developer Tools** - SQL export, data generation, and more

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+ with PDO, MySQLi extensions
- MySQL/MariaDB database
- Composer (for dependencies)

### Installation

1. **Clone and install:**
   ```bash
   git clone https://github.com/Uptura/flexiapi.git
   cd flexiapi
   composer install
   ```

2. **Setup the framework:**
   ```bash
   php bin/flexiapi setup
   ```

3. **Create your first endpoint:**
   ```bash
   php bin/flexiapi create users
   ```

4. **Start developing:**
   ```bash
   php bin/flexiapi serve
   ```

Your API is now running at `http://localhost:8000`! 🎉

## 📖 Complete Command Reference

### 🔧 Setup & Configuration
```bash
# Interactive setup wizard
php bin/flexiapi setup
php bin/flexiapi init                    # Alias

# Show system information
php bin/flexiapi version
```

### 📝 Endpoint Management
```bash
# Create new endpoint
php bin/flexiapi create:endpoint users
php bin/flexiapi create users            # Alias
php bin/flexiapi new users               # Alias

# Update existing endpoint
php bin/flexiapi update:endpoint users
php bin/flexiapi update users            # Alias
php bin/flexiapi edit users              # Alias

# List all endpoints
php bin/flexiapi list:endpoints
php bin/flexiapi list                    # Alias
php bin/flexiapi ls                      # Alias

# Show detailed endpoint info
php bin/flexiapi list --details
php bin/flexiapi ls --json               # JSON output
php bin/flexiapi ls --csv                # CSV output
```

### 📤 Export & Generation
```bash
# Generate Postman collection
php bin/flexiapi generate:postman
php bin/flexiapi postman                 # Alias
php bin/flexiapi pm                      # Alias

# Export unified SQL schema
php bin/flexiapi export:sql
php bin/flexiapi export                  # Alias
php bin/flexiapi sql                     # Alias

# Export with sample data
php bin/flexiapi export:sql --data
php bin/flexiapi export:sql --samples=100
```

### 🖥️ Development Server
```bash
# Start development server
php bin/flexiapi serve
php bin/flexiapi server                  # Alias
php bin/flexiapi start                   # Alias

# Custom host and port
php bin/flexiapi serve --host=0.0.0.0 --port=9000

# Enable verbose logging
php bin/flexiapi serve --verbose
php bin/flexiapi serve -v
```

### 💡 Help & Documentation
```bash
# Show all commands
php bin/flexiapi help

# Get help for specific command
php bin/flexiapi create:endpoint --help
php bin/flexiapi serve --help
```

## 🎯 Workflow Examples

### Creating a Blog API
```bash
# Setup the framework
php bin/flexiapi setup

# Create blog posts endpoint
php bin/flexiapi create posts
# Add: title (string), content (text), author_id (integer), published_at (datetime)

# Create authors endpoint  
php bin/flexiapi create authors
# Add: name (string), email (string), bio (text)

# Create categories endpoint
php bin/flexiapi create categories
# Add: name (string), description (text)

# Generate Postman collection
php bin/flexiapi postman

# Start development server
php bin/flexiapi serve --verbose
```

### E-commerce API Setup
```bash
# Products endpoint
php bin/flexiapi create products
# Add: name, description, price, category_id, stock_quantity, sku

# Orders endpoint
php bin/flexiapi create orders
# Add: user_id, total_amount, status, order_date

# Generate complete SQL schema
php bin/flexiapi export:sql --data --samples=50

# Test with built-in server
php bin/flexiapi serve --port=8080
```

## 🛡️ Security Features

### Rate Limiting
FlexiAPI includes built-in rate limiting middleware:

```php
// Automatically configured during setup
// Default: 100 requests per minute per IP
// Customizable via config/config.php
```

### Authentication
Multiple authentication methods supported:
- **API Keys** - Simple key-based authentication
- **JWT Tokens** - JSON Web Token support
- **Session-based** - Traditional session authentication

### Input Validation
All endpoints include automatic:
- Type validation
- Required field checks
- SQL injection prevention
- XSS protection

## 📊 API Endpoints

Each created endpoint automatically provides:

### Standard CRUD Operations
```http
GET    /api/{endpoint}           # List all records
GET    /api/{endpoint}/{id}      # Get single record
POST   /api/{endpoint}           # Create new record
PUT    /api/{endpoint}/{id}      # Update record
DELETE /api/{endpoint}/{id}      # Delete record
```

### Advanced Features
```http
GET    /api/{endpoint}?limit=10&offset=20    # Pagination
GET    /api/{endpoint}?sort=name&order=desc  # Sorting
GET    /api/{endpoint}?filter[name]=john     # Filtering
GET    /api/{endpoint}?search=keyword        # Search
```

### Meta Endpoints
```http
GET    /api/health              # System health check
GET    /api/schema/{endpoint}   # Endpoint schema
GET    /api/stats               # Usage statistics
```

## 🔧 Configuration

### Database Configuration
```php
// config/config.php
return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'rate_limiting' => [
        'enabled' => true,
        'requests_per_minute' => 100,
        'storage' => 'file' // or 'memory'
    ],
    'authentication' => [
        'method' => 'api_key', // 'api_key', 'jwt', 'session'
        'required' => false
    ]
];
```

## 📚 Advanced Usage

### Custom Methods
Add custom business logic to any endpoint:

```php
// src/Custom/UserMethods.php
class UserMethods {
    public function login($userData) {
        // Custom login logic
    }
    
    public function resetPassword($email) {
        // Password reset logic
    }
}
```

## 🚀 Deployment

### Production Setup
```bash
# Generate production config
php bin/flexiapi export:sql --production

# Optimize for production
composer install --no-dev --optimize-autoloader

# Setup web server (Apache/Nginx)
# Point document root to: public/
```

## 🔍 Troubleshooting

### Common Issues

**Database Connection Failed**
```bash
# Check database credentials
php bin/flexiapi setup
# Verify MySQL service is running
```

**Permission Errors**
```bash
# Fix file permissions (Unix/Linux)
chmod -R 755 public/
chmod -R 777 storage/
```

### Debug Mode
```bash
# Enable verbose logging
php bin/flexiapi serve --verbose

# Check system status
php bin/flexiapi version

# Validate configuration
php bin/flexiapi setup --validate
```

## 📄 License

This project is licensed under the MIT License.

---

**Made with ❤️ by the Uptura Team**

⭐ **Star us on GitHub** if FlexiAPI helps you build amazing APIs!