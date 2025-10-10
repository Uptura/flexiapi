# Changelog

All notable changes to FlexiAPI will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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