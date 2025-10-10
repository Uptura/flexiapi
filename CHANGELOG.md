# Changelog

All notable changes to FlexiAPI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.4.0] - 2025-10-10

### 🚀 Release
- **Version Bump** - All references updated to 3.4.0
- **Deployment Ready** - Banner and CLI parse issues resolved

## [3.3.0] - 2025-10-10

### 🛠️ Critical Fix
- **CLI Banner Parse Error Fixed** - Banner now uses PHP heredoc for compatibility
- **Version Bump** - All references updated to 3.3.0

## [3.2.0] - 2025-10-10

### 🔧 Fixes & Polishing
- **CLI Banners Updated** - Version references aligned to 3.2.0
- **Deployment Docs** - Refreshed with 3.2.0 commands and checks
- **Composer Metadata** - Branch alias and framework-version bumped to 3.2.x

### ✅ Validation
- Verified clean install and tag push process for new release series

## [3.1.0] - 2025-10-10

### 🔧 Bug Fixes & Improvements
- **Setup Command Enhancement** - Fixed deployment issues with proper CORS configuration creation
- **Package Deployment** - Resolved conflicts with legacy package references
- **CLI Stability** - Improved error handling and setup process reliability
- **Documentation Updates** - Enhanced deployment guides and troubleshooting sections

### 🚀 Deployment Enhancements
- **Clean Package Installation** - Streamlined installation process without conflicts
- **Improved Setup Process** - Better error handling and configuration validation
- **Package Compatibility** - Resolved composer cache and dependency conflicts

## [3.0.0] - 2025-10-10

### 🚀 Major Features
- **Field-Level Encryption** - AES-256-CBC encryption with CLI configuration for sensitive data
- **Advanced Search & Pagination** - Full-text search, column-specific search, sorting, and pagination
- **Dynamic CORS Configuration** - CLI-based CORS policy management with live updates
- **Custom Authentication Headers** - Enhanced JWT with Auth-x header support
- **Dynamic CLI Detection** - Automatically detects installation type (global, local, development)
- **Enhanced Documentation** - Comprehensive README with examples for all features

### 🔐 Security Enhancements
- **Custom Auth-x Header** - Improved authentication with custom header detection
- **Bearer Token Enhancement** - Robust token parsing and validation
- **Enhanced Header Processing** - Comprehensive authentication header support for all HTTP methods
- **Field Encryption CLI** - Interactive encryption configuration during endpoint updates

### 📊 Query & Search Features
- **Pagination Support** - `?page=1&limit=10` with response metadata
- **Global Search** - `?search=query` across all searchable fields
- **Column Search** - `/search/{column}?q=value` for specific field queries
- **Sorting** - `?sort=field&order=ASC/DESC` for result ordering
- **Combined Queries** - Support for multiple query parameters simultaneously

### 🌐 CORS Management
- **Interactive CORS CLI** - `flexiapi configure:cors` command
- **Dynamic Configuration** - Origins, methods, headers, credentials, max-age
- **Live Updates** - Automatic index.php updates with fallback support
- **Multiple Aliases** - `cors`, `config:cors` command shortcuts

### 🔧 CLI Improvements
- **Smart Command Detection** - Shows correct command prefix (flexiapi vs vendor/bin/flexiapi vs php bin/flexiapi)
- **Enhanced Help System** - Context-aware help with proper command examples
- **New Commands** - `configure:cors` with full CORS management
- **Improved Aliases** - More intuitive command shortcuts

### 🛠️ Development Experience
- **Auto-Detection** - Framework automatically detects installation context
- **Debug File Cleanup** - Removed development test files, added to .gitignore
- **Enhanced Error Handling** - Better error messages and validation
- **Comprehensive Examples** - Full CRUD examples with all new features

### 📚 Documentation Updates
- **Complete README Overhaul** - Comprehensive documentation with all v3.0.0 features
- **Deployment Guide Updates** - v3.0.0 specific deployment instructions
- **API Examples** - Complete examples for all HTTP methods with authentication
- **Feature Documentation** - Detailed guides for encryption, search, CORS, and authentication

### 🔄 Breaking Changes
- **Auth Header Change** - Default authentication now uses `Auth-x` header instead of `Authorization`
- **GET Authentication** - All GET endpoints now require authentication by default
- **CLI Output Format** - Dynamic command prefix detection changes help text

### 🐛 Bug Fixes
- **PUT Method Authentication** - Fixed authentication header detection for PUT requests
- **Bearer Token Parsing** - Improved token format validation and parsing
- **CORS Header Handling** - Fixed CORS header configuration and application
- **CLI Command Detection** - Fixed command prefix detection across different installation types

## [2.0.0] - 2025-10-10

### Added
- **Complete CLI Framework** - Full-featured command-line interface with aliases and help system
- **UpdateEndpointCommand** - Interactive endpoint modification with column management
- **GeneratePostmanCommand** - Automatic Postman collection generation with authentication setup
- **ExportSqlCommand** - Unified SQL export with sample data generation options
- **ServeCommand** - Built-in development server with logging, CORS, and verbose mode
- **ListEndpointsCommand** - Comprehensive endpoint overview with multiple output formats (table, JSON, CSV)
- **RateLimitMiddleware** - Request throttling with configurable storage backends (file/memory)
- **Enhanced CLI Experience** - Command suggestions for typos, colored output, progress indicators
- **Command Aliases** - Intuitive shortcuts (create, update, list, serve, etc.)
- **Comprehensive Help System** - Detailed command documentation with examples
- **Cross-platform Support** - Windows, Linux, and macOS compatibility
- **Security Features** - Input validation, SQL injection prevention, XSS protection
- **Professional Documentation** - Complete README with deployment guides and examples

### Enhanced
- **BaseCommand Architecture** - Consistent command inheritance with standardized interfaces
- **Error Handling** - Comprehensive error messages with actionable suggestions
- **Configuration Management** - Improved config handling with validation
- **File Management** - Robust file operations with proper error handling
- **Terminal Output** - Professional formatting with colors and icons

### Technical
- **PHP 8.0+ Requirement** - Modern PHP features and performance improvements
- **Zero External Dependencies** - Pure PHP implementation (except Composer autoloading)
- **PSR-4 Autoloading** - Standard autoloading for professional package structure
- **Middleware Architecture** - Extensible middleware system for custom functionality
- **File-based Storage** - Efficient JSON and SQL file management

### Developer Experience
- **Interactive Prompts** - Guided setup and configuration processes
- **Intelligent Validation** - Real-time input validation with helpful error messages
- **Command Suggestions** - Smart suggestions for mistyped commands
- **Verbose Logging** - Detailed logging options for debugging and monitoring
- **Multiple Output Formats** - Flexible data export in table, JSON, and CSV formats

## [1.0.0] - 2025-10-09

### Added
- **Initial Release** - Basic API endpoint creation functionality
- **SetupCommand** - Interactive database and framework configuration
- **CreateEndpointCommand** - Basic endpoint creation with CRUD operations
- **Core Framework** - FlexiAPI main class with routing and database integration
- **Authentication Support** - JWT and API key authentication methods
- **Database Integration** - MySQL adapter with PDO support
- **Basic CLI** - Simple command-line interface

### Features
- Basic CRUD endpoint generation
- Database table creation
- Controller generation
- Simple routing system
- JSON response handling
- Input validation
- Basic error handling

---

## Release Notes

### v2.0.0 - The Complete Framework
This major release transforms FlexiAPI from a basic tool into a comprehensive, production-ready API development framework. With 8 new major commands and extensive CLI enhancements, developers can now build, test, and deploy APIs faster than ever.

**Key Highlights:**
- **8 New Commands** - Complete API lifecycle management
- **Professional CLI** - Enterprise-grade command-line experience  
- **Built-in Dev Server** - No external dependencies for development
- **Auto-Documentation** - Generate Postman collections automatically
- **Rate Limiting** - Built-in security and performance controls
- **Cross-platform** - Works seamlessly on Windows, Linux, and macOS

### Upgrade Guide
To upgrade from v1.x to v2.0:

1. **Backup your existing endpoints and config**
2. **Update via Composer**: `composer update uptura/flexiapi`
3. **Run migration**: `vendor/bin/flexiapi setup --migrate`
4. **Test your endpoints**: `vendor/bin/flexiapi list --details`

### Breaking Changes
- Minimum PHP version increased to 8.0
- Command structure updated (old commands still work via aliases)
- Configuration file format enhanced (auto-migrated during setup)

### Support
- **Documentation**: [GitHub README](https://github.com/uptura/flexiapi#readme)
- **Issues**: [GitHub Issues](https://github.com/uptura/flexiapi/issues)
- **Email**: support@uptura.com