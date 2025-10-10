# FlexiAPI v3.4.0 Deployment & Publishing Guide

This comprehensive guide covers deployment, publishing, and distribution of FlexiAPI Framework v3.4.0 with all features including encryption, advanced search, CORS configuration, and dynamic CLI.

## 📦 Publishing to Packagist (Composer)

### Prerequisites
1. **GitHub Repository** - Your code must be in a public GitHub repository
2. **Packagist Account** - Create account at [packagist.org](https://packagist.org)
3. **Git Tags** - Use semantic versioning for releases

### Step-by-Step Publication

#### 1. Prepare Your Repository

```bash
# Ensure all files are committed
git add .
git commit -m "Prepare for v3.4.0 release - Framework updates and deployment improvements"

# Create a release tag
git tag -a v3.4.0 -m "FlexiAPI v3.4.0 - Stability and Deployment Improvements"

# Push to GitHub
git push origin main
git push origin v3.4.0
```

#### 2. Validate Package Structure

```bash
# Test composer.json syntax
composer validate

# Test autoloading (includes new CORS command)
composer dump-autoload

# Verify package installation locally
composer install --no-dev

# Test new CLI features
flexiapi --version  # Should show v3.4.0
flexiapi cors       # Test CORS configuration
```

#### 3. Submit to Packagist

1. **Login to Packagist**: Go to [packagist.org](https://packagist.org) and sign in
2. **Submit Package**: Click "Submit" and enter your GitHub repository URL:
   ```
   https://github.com/Uptura/flexiapi
   ```
3. **Auto-update Setup**: Configure GitHub webhook for automatic updates
4. **Verify Listing**: Your package will be available at `https://packagist.org/packages/uptura/flexiapi`

#### 4. Test Installation

```bash
# Test global installation
composer global require uptura-official/flexiapi

# Test project installation
composer require uptura-official/flexiapi

# Verify CLI availability and features
vendor/bin/flexiapi --version       # Should show v3.4.0
vendor/bin/flexiapi cors            # Test CORS configuration
vendor/bin/flexiapi list            # Test endpoint listing
```

## 🚀 Installation Methods for v3.4.0

### Method 1: Global Installation (Recommended)

```bash
# Install globally
composer global require uptura-official/flexiapi

# Make sure ~/.composer/vendor/bin is in your PATH
export PATH="$PATH:$HOME/.composer/vendor/bin"

# Use from anywhere with dynamic CLI detection
flexiapi setup                      # Auto-detects global installation
flexiapi create users               # Creates endpoints with encryption support
flexiapi configure:cors             # CORS configuration
```

### Method 2: Project-specific Installation

```bash
# Add to existing project
composer require uptura-official/flexiapi

# Use with vendor/bin prefix (automatically detected)
vendor/bin/flexiapi setup           # CLI shows correct prefix
vendor/bin/flexiapi create posts    # Advanced endpoint creation
vendor/bin/flexiapi cors            # CORS configuration
```

### Method 3: Development Installation

```bash
# Clone repository for development
git clone https://github.com/Uptura/flexiapi.git
cd flexiapi
composer install

# Development mode (shows php bin/flexiapi in help)
php bin/flexiapi setup
php bin/flexiapi create:endpoint products --encrypt
```
cd flexiapi

# Install dependencies
composer install

# Use directly
php bin/flexiapi setup
```

## 🌐 Deployment Options

### 1. Traditional Web Hosting

#### Apache Configuration
```apache
# .htaccess in public/ directory
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/flexiapi/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security
    location ~ /\. {
        deny all;
    }
}
```

### 2. Docker Deployment

#### Dockerfile
```dockerfile
FROM php:8.0-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Install composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/api/health || exit 1
```

#### docker-compose.yml
```yaml
version: '3.8'

services:
  flexiapi:
    build: .
    ports:
      - "8080:80"
    environment:
      - DB_HOST=database
      - DB_NAME=flexiapi
      - DB_USER=flexiapi
      - DB_PASS=secure_password
    depends_on:
      - database
    volumes:
      - ./storage:/var/www/html/storage

  database:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: flexiapi
      MYSQL_USER: flexiapi
      MYSQL_PASSWORD: secure_password
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

volumes:
  mysql_data:
```

### 3. Cloud Deployment

#### Heroku
```bash
# Install Heroku CLI and login
heroku login

# Create app
heroku create your-flexiapi-app

# Add MySQL addon
heroku addons:create cleardb:ignite

# Configure environment
heroku config:set APP_ENV=production

# Deploy
git push heroku main

# Run setup
heroku run vendor/bin/flexiapi setup
```

#### DigitalOcean App Platform
```yaml
# .do/app.yaml
name: flexiapi
services:
- name: web
  source_dir: /
  github:
    repo: your-username/flexiapi
    branch: main
  run_command: vendor/bin/flexiapi serve --host=0.0.0.0 --port=8080
  environment_slug: php
  instance_count: 1
  instance_size_slug: basic-xxs
  routes:
  - path: /
  envs:
  - key: APP_ENV
    value: production
databases:
- name: flexiapi-db
  engine: MYSQL
  version: "8"
  size_slug: db-s-1vcpu-1gb
```

#### AWS EC2
```bash
# Launch EC2 instance with Amazon Linux 2
# Install dependencies
sudo yum update -y
sudo amazon-linux-extras install php8.0 -y
sudo yum install httpd mysql git composer -y

# Clone and setup
cd /var/www/html
sudo git clone https://github.com/uptura/flexiapi.git .
sudo composer install --no-dev
sudo chown -R apache:apache /var/www/html
sudo chmod -R 755 /var/www/html

# Start services
sudo systemctl start httpd
sudo systemctl enable httpd

# Configure RDS database connection
sudo vendor/bin/flexiapi setup
```

## 🔧 Production Configuration

### Environment Variables
```bash
# .env file for production
APP_ENV=production
APP_DEBUG=false
DB_HOST=your-db-host
DB_NAME=your-database
DB_USER=your-username
DB_PASS=your-secure-password
API_SECRET_KEY=your-secret-key-here
RATE_LIMIT_ENABLED=true
CORS_ENABLED=true
LOG_LEVEL=info
```

### Security Checklist

- [ ] **Database Security**
  - Use strong passwords
  - Limit database user permissions
  - Enable SSL connections
  - Regular backups

- [ ] **Application Security**
  - Set `APP_DEBUG=false` in production
  - Use HTTPS only
  - Configure proper CORS settings
  - Enable rate limiting
  - Regular security updates

- [ ] **Server Security**
  - Keep PHP and extensions updated
  - Configure firewall rules
  - Disable unnecessary PHP functions
  - Set proper file permissions
  - Enable security headers

### Performance Optimization

```bash
# Optimize Composer autoloader
composer install --no-dev --optimize-autoloader --classmap-authoritative

# Enable OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60

# Set production PHP settings
memory_limit=512M
max_execution_time=30
upload_max_filesize=10M
post_max_size=10M
```

## 📊 Monitoring & Maintenance

### Health Checks
```bash
# Built-in health endpoint
curl https://your-api.com/api/health

# CLI health check
vendor/bin/flexiapi version
vendor/bin/flexiapi list --details
```

### Logging
```bash
# Enable verbose logging
vendor/bin/flexiapi serve --verbose

# Check system logs
tail -f /var/log/apache2/error.log
tail -f storage/logs/flexiapi.log
```

### Backup Strategy
```bash
# Database backup
mysqldump -h host -u user -p database > backup_$(date +%Y%m%d).sql

# Application backup
tar -czf flexiapi_backup_$(date +%Y%m%d).tar.gz /var/www/html
```

## 🔄 Update Process

### For Package Users
```bash
# Update to latest version
composer update uptura/flexiapi

# Check for breaking changes
vendor/bin/flexiapi version
cat vendor/uptura/flexiapi/CHANGELOG.md
```

### For Contributors
```bash
# Create new release
git tag -a v2.1.0 -m "FlexiAPI v2.1.0 - New Features"
git push origin v2.1.0

# Packagist will auto-update via webhook
```

## 🐛 Troubleshooting

### Common Issues

**Composer Installation Fails**
```bash
# Clear composer cache
composer clear-cache

# Update composer
composer self-update

# Install with verbose output
composer require uptura/flexiapi -vvv
```

**Permission Issues**
```bash
# Fix file permissions
chmod -R 755 /var/www/html
chown -R www-data:www-data /var/www/html

# For storage directory
chmod -R 777 storage/
```

**Database Connection Issues**
```bash
# Test database connection
vendor/bin/flexiapi setup --test-db

# Check database credentials
cat config/config.php
```

## 📞 Support

- **Documentation**: [GitHub README](https://github.com/Uptura/flexiapi#readme)
- **Issues**: [GitHub Issues](https://github.com/Uptura/flexiapi/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Uptura/flexiapi/discussions)
- **Email**: support@uptura.com

---

**Ready to deploy? Choose your preferred method above and start building amazing APIs!** 🚀