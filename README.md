# FlexiAPI Framework

A powerful CLI-based API development framework for rapid endpoint creation.

## Quick Start

1. **Setup the framework:**
   ```bash
   flexiapi setup
   ```

2. **Create your first endpoint:**
   ```bash
   flexiapi create:endpoint users
   ```

3. **Start the development server:**
   ```bash
   flexiapi serve
   ```

## Available Commands

- `flexiapi setup` - Initial framework setup
- `flexiapi create:endpoint <name>` - Create a new API endpoint
- `flexiapi update:endpoint <name>` - Update an existing endpoint
- `flexiapi generate:postman` - Generate Postman collection
- `flexiapi export:sql` - Export all SQL schemas

## Documentation

Your API endpoints will be available at:
- `GET /api/v1/{endpoint}` - List all records
- `POST /api/v1/{endpoint}` - Create new record
- `GET /api/v1/{endpoint}/{id}` - Get specific record
- `PUT /api/v1/{endpoint}/{id}` - Update record
- `DELETE /api/v1/{endpoint}/{id}` - Delete record

## Authentication

Use the `/api/v1/auth/generate_keys` endpoint to generate API keys for authentication.

## Features

- ✅ Rapid endpoint creation
- ✅ JWT Authentication
- ✅ Data validation
- ✅ Encryption support
- ✅ Rate limiting
- ✅ CORS handling
- ✅ Postman collection generation
- ✅ SQL export functionality